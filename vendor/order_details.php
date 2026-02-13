<?php
session_start();
require_once '../includes/db.php';

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

try {
    $db = Database::connect();

    if (!isset($_GET['id'])) {
        die("رقم الطلب غير موجود.");
    }

    $order_id = new MongoDB\BSON\ObjectId($_GET['id']);
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

    // جلب معرف المتجر
    $vendor = $db->vendors->findOne(['user_id' => $user_id]);
    if (!$vendor) die("حساب التاجر غير موجود");
    
    $vendor_id = $vendor['_id'];
    $store_name = $vendor['store_name'];

    // 2. جلب الطلب (يجب أن يطابق رقم الطلب ومعرف التاجر)
    $pipeline = [
        ['$match' => [
            '_id' => $order_id,
            'vendor_id' => $vendor_id // حماية: التأكد أن الطلب يخص هذا التاجر
        ]],
        ['$lookup' => [
            'from' => 'users',
            'localField' => 'customer_id',
            'foreignField' => '_id',
            'as' => 'customer_data'
        ]],
        ['$unwind' => '$customer_data'],
        ['$project' => [
            'status' => 1,
            'payment_method' => 1,
            'payment_receipt' => 1, // إضافة حقل السند
            'delivery_address' => 1,
            'created_at' => 1,
            'customer_name' => ['$concat' => ['$customer_data.first_name', ' ', '$customer_data.last_name']],
            'customer_phone' => '$customer_data.phone',
            'vendor_items' => '$items' // بما أن الطلب مفصول، فكل العناصر تخص التاجر
        ]]
    ];

    $result = $db->orders->aggregate($pipeline)->toArray();

    if (empty($result) || empty($result[0]['vendor_items'])) {
        die("عذراً، لا تملك صلاحية عرض هذا الطلب أو لا توجد منتجات لك فيه.");
    }

    $order = $result[0];
    $items = $order['vendor_items'];

    // حساب إجمالي التاجر
    $vendor_total = 0;
    foreach ($items as $item) {
        $vendor_total += ($item['price'] * $item['quantity']);
    }

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب #<?php echo substr((string)$order['_id'], -6); ?> | لوحة التاجر</title>
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
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        @media print {
            .no-print { display: none; }
            body { background: white; color: black; }
            .glass-card { background: none; border: 1px solid #ccc; color: black; }
            .text-white { color: black !important; }
            .text-slate-400 { color: #666 !important; }
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        
        <!-- رأس الصفحة -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 no-print gap-4">
            <div>
                <h1 class="text-3xl font-bold mb-2">تفاصيل الطلب <span class="text-brand-gold font-mono">#<?php echo substr((string)$order['_id'], -6); ?></span></h1>
                <p class="text-slate-400">تاريخ الطلب: <?php echo $order['created_at']->toDateTime()->format('Y/m/d h:i A'); ?></p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fas fa-print"></i> طباعة الفاتورة
                </button>
                <a href="orders.php" class="bg-brand-accent hover:bg-blue-600 text-white px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> عودة للقائمة
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- معلومات العميل -->
            <div class="glass-card rounded-2xl p-6 md:col-span-2">
                <h3 class="font-bold text-lg mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                    <i class="fas fa-user-circle text-brand-gold"></i> معلومات العميل
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="block text-slate-400 text-sm">الاسم الكامل</span>
                        <span class="font-bold text-lg"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div>
                        <span class="block text-slate-400 text-sm">رقم الهاتف</span>
                        <span class="font-bold font-mono text-lg"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                    </div>
                    <div class="md:col-span-2">
                        <span class="block text-slate-400 text-sm">عنوان التوصيل</span>
                        <span class="font-bold text-white bg-slate-800/50 p-2 rounded-lg block mt-1">
                            <?php echo !empty($order['delivery_address']) ? htmlspecialchars($order['delivery_address']) : 'لم يحدد عنواناً تفصيلياً (استلام من الفرع أو تواصل هاتفي)'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ملخص الحالة (تم التحديث هنا) -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                    <i class="fas fa-info-circle text-brand-gold"></i> حالة الطلب
                </h3>
                <div class="space-y-4">
                    <div>
                        <span class="block text-slate-400 text-sm mb-1">الحالة الحالية</span>
                        <?php 
                            $status_color = match($order['status']) {
                                'pending' => 'text-yellow-500 bg-yellow-500/10',
                                'completed', 'delivered' => 'text-green-500 bg-green-500/10',
                                'cancelled' => 'text-red-500 bg-red-500/10',
                                'processing' => 'text-blue-400 bg-blue-500/10',
                                'shipped' => 'text-purple-500 bg-purple-500/10',
                                default => 'text-slate-400 bg-slate-500/10'
                            };
                        ?>
                        <span class="inline-block px-3 py-1 rounded-full font-bold <?php echo $status_color; ?>">
                            <?php 
                                $status_text = match($order['status']) {
                                    'pending' => 'قيد الانتظار',
                                    'processing' => 'جاري التجهيز',
                                    'shipped' => 'تم الشحن',
                                    'delivered' => 'تم التسليم',
                                    'cancelled' => 'ملغي',
                                    default => $order['status']
                                };
                                echo $status_text; 
                            ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-slate-400 text-sm mb-1">طريقة الدفع</span>
                        <span class="font-bold flex flex-col gap-2">
                            <?php if(isset($order['payment_method']) && $order['payment_method'] == 'cod'): ?>
                                <span class="flex items-center gap-2 text-green-400"><i class="fas fa-money-bill-wave"></i> دفع عند الاستلام</span>
                            <?php else: ?>
                                <span class="flex items-center gap-2 text-brand-gold"><i class="fas fa-wallet"></i> محفظة / تحويل</span>
                                <?php if(isset($order['payment_receipt']) && !empty($order['payment_receipt'])): ?>
                                    <a href="../<?php echo $order['payment_receipt']; ?>" target="_blank" class="text-xs bg-blue-500/20 text-blue-400 hover:bg-blue-500 hover:text-white px-3 py-2 rounded-lg transition-colors flex items-center justify-center gap-2 border border-blue-500/30">
                                        <i class="fas fa-paperclip"></i> عرض سند التحويل
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-red-400 flex items-center gap-1"><i class="fas fa-times-circle"></i> لا يوجد سند مرفق</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول المنتجات المطلوب تجهيزها -->
        <div class="glass-card rounded-2xl overflow-hidden shadow-2xl">
            <div class="p-6 bg-slate-800/50 border-b border-slate-700 flex justify-between items-center">
                <h3 class="font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-box-open text-brand-gold"></i> المنتجات المطلوب تجهيزها
                </h3>
                <span class="text-sm bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full">
                    <?php echo count($items); ?> أصناف
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-right">
                    <thead class="bg-slate-900/50 text-slate-400 text-sm">
                        <tr>
                            <th class="p-4">المنتج</th>
                            <th class="p-4 text-center">الكمية</th>
                            <th class="p-4 text-center">سعر الوحدة</th>
                            <th class="p-4 text-center">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td class="p-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-lg bg-slate-700 overflow-hidden border border-slate-600 flex-shrink-0">
                                        <?php if(isset($item['image']) && $item['image']): ?>
                                            <img src="../<?php echo $item['image']; ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="flex items-center justify-center h-full text-slate-500"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-lg"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <div class="text-slate-400 text-xs">كود المنتج: <?php echo substr((string)$item['product_id'], -6); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span class="bg-slate-700 text-white font-bold px-3 py-1 rounded-lg">x <?php echo $item['quantity']; ?></span>
                            </td>
                            <td class="p-4 text-center text-slate-300">
                                <?php echo number_format($item['price']); ?> ر.ي
                            </td>
                            <td class="p-4 text-center font-bold text-brand-gold">
                                <?php echo number_format($item['price'] * $item['quantity']); ?> ر.ي
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-900/80 border-t border-slate-700">
                        <tr>
                            <td colspan="3" class="p-6 text-left text-slate-400 font-bold">إجمالي المبلغ المستحق لك في هذا الطلب:</td>
                            <td class="p-6 text-center text-2xl font-black text-brand-gold">
                                <?php echo number_format($vendor_total); ?> <span class="text-sm font-normal text-white">ر.ي</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="mt-8 text-center no-print">
            <a href="https://wa.me/<?php echo preg_replace('/^0/', '967', $order['customer_phone']); ?>" target="_blank" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-lg hover:shadow-green-500/30">
                <i class="fab fa-whatsapp text-xl"></i> تواصل مع العميل عبر واتساب
            </a>
        </div>

    </div>

</body>
</html>