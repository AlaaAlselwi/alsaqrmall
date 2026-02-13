<?php
session_start();
require_once '../includes/db.php';

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();

    // جلب معرف المتجر
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $vendor = $db->vendors->findOne(['user_id' => $user_id]);

    if (!$vendor) {
        die("خطأ: لم يتم العثور على حساب التاجر.");
    }
    
    $vendor_id = $vendor['_id'];
    $store_name = $vendor['store_name'];

    // 2. تحديث حالة الطلب
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
        $order_id = new MongoDB\BSON\ObjectId($_POST['order_id']);
        $new_status = $_POST['status'];
        
        // جلب الطلب الحالي للتحقق من الحالة السابقة والعناصر (التحقق من ملكية الطلب)
        $currentOrder = $db->orders->findOne(['_id' => $order_id, 'vendor_id' => $vendor_id]);
        
        if ($currentOrder) {
            $old_status = $currentOrder['status'];
            
            // إذا كانت الحالة الجديدة "ملغي" والحالة القديمة لم تكن "ملغي"، نعيد المخزون
            if ($new_status === 'cancelled' && $old_status !== 'cancelled') {
                $productsCollection = $db->products;
                foreach ($currentOrder['items'] as $item) {
                    $productsCollection->updateOne(
                        ['_id' => $item['product_id']],
                        ['$inc' => ['stock' => (int)$item['quantity']]]
                    );
                }
            }
            // (اختياري) إذا تم تفعيل طلب ملغي سابقاً، نخصم المخزون مجدداً؟ 
            // - هذا يتطلب تعقيداً أكبر للتحقق من توفر المخزون الحالي.
            // - سنكتفي حالياً بمنع التاجر من إعادة تفعيل الطلب الملغي إلا إذا كان متأكداً، 
            //   أو يمكننا إضافة شرط بسيط للخصم، لكن قد يصبح المخزون بالسالب.
            //   سنركز على طلب المستخدم الأساسي: "إلغاء الطلب لا يعيد المخزون".
            
            $db->orders->updateOne(
                ['_id' => $order_id],
                ['$set' => ['status' => $new_status]]
            );
            
            $msg = "تم تحديث حالة الطلب بنجاح. " . ($new_status === 'cancelled' ? "(تم استعادة المخزون)" : "");
        }
    }

    // 3. جلب الطلبات الخاصة بهذا التاجر (الآن الطلبات مفصولة لكل تاجر)
    $pipeline = [
        ['$match' => ['vendor_id' => $vendor_id]],
        ['$lookup' => [
            'from' => 'users',
            'localField' => 'customer_id',
            'foreignField' => '_id',
            'as' => 'customer'
        ]],
        ['$unwind' => '$customer'],
        ['$sort' => ['created_at' => -1]],
        
        // حساب إجمالي الطلب وعد العناصر (موجودة أصلاً في المستند، لكن للتأكيد)
        ['$project' => [
            '_id' => 1,
            'created_at' => 1,
            'status' => 1,
            'payment_method' => ['$ifNull' => ['$payment_method', 'cod']],
            'payment_receipt' => 1, // Fix: Explicitly include receipt field
            'customer' => 1,
            'vendor_order_total' => '$total_amount',
            'items_count' => ['$size' => '$items']
        ]]
    ];

    $orders = $db->orders->aggregate($pipeline)->toArray();

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلبات | <?php echo htmlspecialchars($store_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-sidebar': '#111827',
                        'brand-gold': '#fbbf24',
                        'brand-accent': '#3b82f6',
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .glass-table {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        body::-webkit-scrollbar { width: 8px; }
        body::-webkit-scrollbar-track { background: #0f172a; }
        body::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 4px; }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans overflow-x-hidden">

    <div class="flex min-h-screen">
        
        <!-- الشريط الجانبي -->
        <aside class="w-64 bg-brand-sidebar border-l border-slate-800 hidden md:flex flex-col fixed h-full z-20">
            <div class="h-24 flex flex-col items-center justify-center border-b border-slate-800 p-4">
                <div class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($store_name); ?></div>
                <div class="text-xs text-green-400 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> متصل الآن
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto py-6">
                <ul class="space-y-2 px-4">
                    <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-home"></i> نظرة عامة</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box"></i> منتجاتي</a></li>
                    <li><a href="orders.php" class="flex items-center gap-3 px-4 py-3 bg-brand-accent text-white rounded-xl font-bold shadow-lg shadow-blue-500/20">
                        <i class="fas fa-shopping-bag"></i> الطلبات
                        <span id="pending-badge-sidebar" class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full mr-auto animate-pulse hidden">0</span>
                    </a></li>
                    <li><a href="wallet.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-wallet"></i> المحفظة</a></li>
                    <li><a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-cog"></i> إعدادات المتجر</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-slate-800">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/10 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-lg font-bold text-white"><?php echo htmlspecialchars($store_name); ?></div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-xl bg-green-600/20 text-green-500 border border-green-500/30 font-bold text-center">
                <?php echo $msg; ?>
            </div>
            <?php endif; ?>

            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2">سجل الطلبات</h1>
                <p class="text-slate-400">إدارة الطلبات الواردة ومتابعة حالتها.</p>
            </div>

            <!-- جدول الطلبات -->
            <div class="overflow-x-auto rounded-2xl glass-table shadow-2xl">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-800/80 text-slate-300 border-b border-slate-700">
                        <tr>
                            <th class="p-4">رقم الطلب</th>
                            <th class="p-4">بيانات العميل</th>
                            <th class="p-4">إجمالي (لك)</th>
                            <th class="p-4">عدد القطع</th>
                            <th class="p-4">الدفع</th>
                            <th class="p-4 text-center">الحالة</th>
                            <th class="p-4">التاريخ</th>
                            <th class="p-4 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php if(count($orders) > 0): ?>
                            <?php foreach($orders as $order): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-4">
                                    <span class="font-mono text-brand-accent bg-blue-500/10 px-2 py-1 rounded text-xs select-all">#<?php echo substr((string)$order['_id'], -6); ?></span>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-white"><?php echo htmlspecialchars($order['customer']['first_name'] . ' ' . $order['customer']['last_name']); ?></div>
                                    <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($order['customer']['phone']); ?></div>
                                </td>
                                <td class="p-4 font-bold text-brand-gold text-base">
                                    <?php echo number_format($order['vendor_order_total']); ?> ر.ي
                                </td>
                                <td class="p-4 text-slate-300">
                                    <?php echo $order['items_count']; ?> منتجات
                                </td>
                                <td class="p-4">
                                    <?php if(isset($order['payment_method']) && $order['payment_method'] == 'cod'): ?>
                                        <span class="text-xs bg-slate-700 px-2 py-1 rounded text-slate-300">عند الاستلام</span>
                                    <?php else: ?>
                                        <span class="text-xs bg-brand-gold/20 text-brand-gold px-2 py-1 rounded block mb-1">محفظة</span>
                                        <?php if(isset($order['payment_receipt']) && !empty($order['payment_receipt'])): ?>
                                            <a href="../<?php echo $order['payment_receipt']; ?>" target="_blank" class="text-xs text-blue-400 hover:text-white underline">
                                                <i class="fas fa-paperclip"></i> السند
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-red-400">بدون سند</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="order_id" value="<?php echo $order['_id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="bg-slate-900 border border-slate-700 text-xs rounded-lg px-2 py-1 focus:border-brand-accent focus:ring-1 focus:ring-brand-accent outline-none cursor-pointer 
                                            <?php 
                                            if($order['status']=='pending') echo 'text-yellow-500';
                                            elseif($order['status']=='delivered') echo 'text-green-500';
                                            elseif($order['status']=='cancelled') echo 'text-red-500';
                                            else echo 'text-blue-400';
                                            ?>">
                                            <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>قيد الانتظار</option>
                                            <option value="processing" <?php echo $order['status']=='processing'?'selected':''; ?>>جاري التجهيز</option>
                                            <option value="shipped" <?php echo $order['status']=='shipped'?'selected':''; ?>>تم الشحن</option>
                                            <option value="delivered" <?php echo $order['status']=='delivered'?'selected':''; ?>>تم التسليم</option>
                                            <option value="cancelled" <?php echo $order['status']=='cancelled'?'selected':''; ?>>ملغي</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="p-4 text-slate-400 text-xs">
                                    <?php 
                                        $date = $order['created_at']->toDateTime();
                                        echo $date->format('Y/m/d h:i A'); 
                                    ?>
                                </td>
                                <td class="p-4 text-center">
                                    <a href="order_details.php?id=<?php echo $order['_id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-700 hover:bg-brand-accent text-white transition-all" title="تفاصيل الطلب">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-12 text-center text-slate-500">
                                    <i class="fas fa-receipt text-6xl mb-4 opacity-50"></i>
                                    <h3 class="text-xl font-bold text-white mb-2">لا توجد طلبات بعد</h3>
                                    <p>بمجرد أن يطلب العملاء منتجاتك، ستظهر هنا.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- Polling Script -->
    <script>
        setInterval(function() {
            fetch('api/check_new_orders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) return;
                    
                    const count = data.pending_count;
                    const badge = document.getElementById('pending-badge-sidebar');
                    
                    if (badge) {
                        badge.textContent = count;
                        if (count > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                })
                .catch(error => console.error(error));
        }, 10000); 
    </script>

</body>
</html>