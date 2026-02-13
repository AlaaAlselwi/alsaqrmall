<?php
session_start();
require_once '../includes/db.php'; // استدعاء مكتبة MongoDB

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

// تهيئة المتغيرات
$store_name = "متجري";
$total_products = 0;
$total_sales = 0;
$pending_orders = 0;
$recent_orders = [];

try {
    $db = Database::connect();
    
    // 2. جلب معرف المتجر (Vendor ID)
    $user_id = $_SESSION['user_id'];
    // تحويل user_id من نص إلى ObjectId
    $userObjectId = new MongoDB\BSON\ObjectId($user_id);
    
    $vendor = $db->vendors->findOne(['user_id' => $userObjectId]);

    if (!$vendor) {
        die("خطأ: لم يتم العثور على بيانات المتجر.");
    }

    $vendor_id = $vendor['_id'];
    $store_name = $vendor['store_name'];

    // 3. الإحصائيات

    // أ. عدد المنتجات
    $total_products = $db->products->countDocuments(['vendor_id' => $vendor_id]);

    // ب. إجمالي المبيعات
    // (بما أن الطلبات مفصولة، نجمع total_amount مباشرة)
    $salesPipeline = [
        ['$match' => [
            'vendor_id' => $vendor_id,
            'status' => ['$ne' => 'cancelled'] // استبعاد الطلبات الملغية
        ]],
        ['$group' => [
            '_id' => null, 
            'total' => ['$sum' => '$total_amount']
        ]]
    ];
    $salesResult = $db->orders->aggregate($salesPipeline)->toArray();
    $total_sales = !empty($salesResult) ? $salesResult[0]['total'] : 0;

    // ج. الطلبات الجديدة
    $pending_orders = $db->orders->countDocuments([
        'status' => 'pending',
        'vendor_id' => $vendor_id
    ]);

    // 4. أحدث الطلبات
    $recentOrdersPipeline = [
        ['$match' => ['vendor_id' => $vendor_id]],
        ['$sort' => ['created_at' => -1]],
        ['$limit' => 5],
        ['$lookup' => [ // جلب اسم العميل
            'from' => 'users',
            'localField' => 'customer_id',
            'foreignField' => '_id',
            'as' => 'customer'
        ]],
        ['$unwind' => '$customer'],
        ['$project' => [
            'id' => '$_id',
            'created_at' => 1,
            'status' => 1,
            'first_name' => '$customer.first_name',
            'last_name' => '$customer.last_name',
            'order_total' => '$total_amount'
        ]]
    ];
    $recent_orders = $db->orders->aggregate($recentOrdersPipeline)->toArray();

} catch (Exception $e) {
    // في حالة الخطأ، القيم ستبقى 0
    error_log("Vendor Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التاجر | <?php echo htmlspecialchars($store_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-sidebar': '#111827', // لون أغمق قليلاً للتاجر
                        'brand-gold': '#fbbf24',
                        'brand-accent': '#3b82f6', // لون أزرق مميز للتاجر
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(30, 41, 59, 0.3);
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
        
        <!-- الشريط الجانبي للتاجر -->
        <aside class="w-64 bg-brand-sidebar border-l border-slate-800 hidden md:flex flex-col fixed h-full z-20">
            <div class="h-24 flex flex-col items-center justify-center border-b border-slate-800 p-4">
                <div class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($store_name); ?></div>
                <div class="text-xs text-green-400 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> متصل الآن
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-6">
                <ul class="space-y-2 px-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-brand-accent text-white rounded-xl font-bold shadow-lg shadow-blue-500/20">
                            <i class="fas fa-home"></i> نظرة عامة
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors">
                            <i class="fas fa-box"></i> منتجاتي
                            <span class="bg-slate-700 text-xs px-2 py-0.5 rounded-full mr-auto"><?php echo $total_products; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors">
                            <i class="fas fa-shopping-bag"></i> الطلبات
                            <span id="pending-badge-sidebar" class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full mr-auto animate-pulse <?php echo $pending_orders > 0 ? '' : 'hidden'; ?>">
                                <?php echo $pending_orders; ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="wallet.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors">
                            <i class="fas fa-wallet"></i> المحفظة
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors">
                            <i class="fas fa-cog"></i> إعدادات المتجر
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/10 rounded-xl transition-colors">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <!-- هيدر الموبايل -->
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-lg font-bold text-white"><?php echo htmlspecialchars($store_name); ?></div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <!-- الترحيب -->
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold mb-2">مرحباً، شريك النجاح 👋</h1>
                <p class="text-slate-400">إليك ما يحدث في متجرك اليوم.</p>
            </div>

            <!-- البطاقات الإحصائية -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                
                <!-- المبيعات -->
                <div class="glass-card rounded-2xl p-6 relative overflow-hidden group">
                    <div class="relative z-10">
                        <div class="text-slate-400 text-sm font-bold mb-2">إجمالي المبيعات</div>
                        <div class="text-3xl font-black text-brand-gold">
                            <?php echo number_format($total_sales); ?> <span class="text-sm font-normal text-white">ر.ي</span>
                        </div>
                    </div>
                    <div class="absolute -bottom-4 -left-4 w-24 h-24 bg-brand-gold/10 rounded-full blur-xl group-hover:bg-brand-gold/20 transition-all"></div>
                    <i class="fas fa-coins absolute top-4 left-4 text-brand-gold/20 text-4xl"></i>
                </div>

                <!-- الطلبات الجديدة -->
                <div class="glass-card rounded-2xl p-6 relative overflow-hidden group">
                    <div class="relative z-10">
                        <div class="text-slate-400 text-sm font-bold mb-2">طلبات جديدة</div>
                        <div class="text-3xl font-black text-white" id="pending-count-card">
                            <?php echo $pending_orders; ?>
                        </div>
                    </div>
                    <div class="absolute -bottom-4 -left-4 w-24 h-24 bg-blue-500/10 rounded-full blur-xl group-hover:bg-blue-500/20 transition-all"></div>
                    <i class="fas fa-shopping-basket absolute top-4 left-4 text-blue-500/20 text-4xl"></i>
                </div>

                <!-- المنتجات -->
                <div class="glass-card rounded-2xl p-6 relative overflow-hidden group">
                    <div class="relative z-10">
                        <div class="text-slate-400 text-sm font-bold mb-2">منتجاتي</div>
                        <div class="text-3xl font-black text-white">
                            <?php echo $total_products; ?>
                        </div>
                    </div>
                    <!-- زر إضافة سريع -->
                    <a href="products.php" class="absolute bottom-4 left-4 bg-brand-accent hover:bg-blue-600 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="إضافة منتج جديد">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>

            <!-- جدول أحدث الطلبات -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="p-6 border-b border-slate-700/50 flex justify-between items-center">
                    <h3 class="font-bold text-lg">أحدث الطلبات</h3>
                    <a href="orders.php" class="text-sm text-brand-accent hover:text-white transition-colors">عرض الكل</a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-slate-800/50 text-slate-400">
                            <tr>
                                <th class="p-4">رقم الطلب</th>
                                <th class="p-4">العميل</th>
                                <th class="p-4">المبلغ</th>
                                <th class="p-4">الحالة</th>
                                <th class="p-4">التاريخ</th>
                                <th class="p-4 text-center">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php if(count($recent_orders) > 0): ?>
                                <?php foreach($recent_orders as $order): ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="p-4 font-mono text-brand-accent">#<?php echo substr((string)$order['id'], -6); ?></td>
                                    <td class="p-4"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td class="p-4 font-bold text-brand-gold"><?php echo number_format($order['order_total']); ?> ر.ي</td>
                                    <td class="p-4">
                                        <?php 
                                            $status_class = 'bg-slate-700 text-slate-300';
                                            if($order['status'] == 'pending') $status_class = 'bg-yellow-500/20 text-yellow-500';
                                            elseif($order['status'] == 'completed') $status_class = 'bg-green-500/20 text-green-500';
                                            elseif($order['status'] == 'cancelled') $status_class = 'bg-red-500/20 text-red-500';
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs <?php echo $status_class; ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-400">
                                        <?php 
                                        // التعامل مع وتاريخ MongoDB
                                        $date = $order['created_at']->toDateTime(); 
                                        echo $date->format('Y/m/d'); 
                                        ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="text-slate-300 hover:text-white bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded-lg transition-colors">
                                            تفاصيل
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500">
                                        لا توجد طلبات حتى الآن. استعد للبيع! 🚀
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- صوت إشعار (اختياري) -->
    <audio id="notification-sound" src="../assets/sounds/notification.mp3" preload="auto"></audio>

    <script>
        // Check for new orders every 10 seconds
        setInterval(function() {
            fetch('api/check_new_orders.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                        return;
                    }

                    const count = data.pending_count;
                    const badge = document.getElementById('pending-badge-sidebar');
                    const cardCount = document.getElementById('pending-count-card');
                    
                    // Update Sidebar Badge
                    if (badge) {
                        badge.textContent = count;
                        if (count > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }

                    // Update Card Count (only on dashboard)
                    if (cardCount) {
                        cardCount.textContent = count;
                    }

                    // Optional: Play sound if count increased (requires storing last count)
                    // For now, just updating UI is enough for "Realism"
                })
                .catch(error => console.error('Error fetching orders:', error));
        }, 10000); // 10 seconds
    </script>

</body>
</html>