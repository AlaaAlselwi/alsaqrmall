<?php
session_start();

// 1. حماية الصفحة
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "alsaqrmall_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!isset($_GET['id'])) {
        header("Location: orders.php");
        exit();
    }

    $order_id = $_GET['id'];
    $customer_id = $_SESSION['user_id'];

    // 2. جلب معلومات الطلب والتأكد أن هذا الزبون هو صاحبه
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("الطلب غير موجود أو لا تملك صلاحية عرضه.");
    }

    // 3. جلب المنتجات داخل هذا الطلب
    $stmt_items = $conn->prepare("
        SELECT oi.*, p.name as product_name, p.image, v.store_name 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN vendors v ON oi.vendor_id = v.id
        WHERE oi.order_id = ?
    ");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب #<?php echo $order_id; ?> | الصقر مول</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-gold': '#fbbf24',
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { background-color: #0f172a; color: white; }
        .glass-panel {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        @media print {
            body { background: white; color: black; }
            .no-print { display: none; }
            .glass-panel { background: none; border: 1px solid #ddd; color: black; }
            .text-white { color: black !important; }
            .text-brand-gold { color: #d97706 !important; } /* لون ذهبي غامق للطباعة */
        }
    </style>
</head>
<body class="font-sans min-h-screen">

    <!-- ناف بار بسيط -->
    <nav class="bg-slate-900 border-b border-white/5 py-4 no-print">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="../index.php" class="text-xl font-bold flex items-center gap-2 hover:text-brand-gold transition">
                <i class="fas fa-home"></i> الرئيسية
            </a>
            <div class="font-bold text-lg text-brand-gold">فاتورة الشراء</div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-4xl">

        <!-- رأس الفاتورة -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold mb-2 flex items-center gap-2">
                    طلب رقم <span class="font-mono text-brand-gold">#<?php echo $order['id']; ?></span>
                </h1>
                <p class="text-slate-400 text-sm">
                    <i class="far fa-calendar-alt ml-1"></i> <?php echo date('Y/m/d h:i A', strtotime($order['created_at'])); ?>
                </p>
            </div>
            <div class="flex gap-3 no-print">
                <button onclick="window.print()" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <a href="orders.php" class="bg-brand-gold hover:bg-yellow-500 text-brand-dark px-4 py-2 rounded-xl font-bold transition-colors">
                    عودة للقائمة
                </a>
            </div>
        </div>

        <!-- حالة الطلب -->
        <div class="glass-panel p-6 rounded-2xl mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center text-xl">
                    <?php if($order['status'] == 'delivered'): ?>
                        <i class="fas fa-check-circle text-green-500"></i>
                    <?php elseif($order['status'] == 'cancelled'): ?>
                        <i class="fas fa-times-circle text-red-500"></i>
                    <?php else: ?>
                        <i class="fas fa-truck text-blue-400"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-sm text-slate-400">حالة الطلب</div>
                    <div class="font-bold text-lg">
                        <?php 
                            $statuses = [
                                'pending' => 'قيد المراجعة',
                                'processing' => 'جاري التجهيز',
                                'shipped' => 'تم الشحن',
                                'delivered' => 'تم التسليم',
                                'cancelled' => 'ملغي'
                            ];
                            echo $statuses[$order['status']] ?? $order['status'];
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="h-10 w-px bg-slate-700 hidden md:block"></div>

            <div class="text-center md:text-right">
                <div class="text-sm text-slate-400">طريقة الدفع</div>
                <div class="font-bold">
                    <?php echo ($order['payment_method'] == 'cod') ? 'دفع عند الاستلام' : 'تحويل بنكي / محفظة'; ?>
                </div>
            </div>

            <div class="h-10 w-px bg-slate-700 hidden md:block"></div>

            <div class="text-center md:text-right">
                <div class="text-sm text-slate-400">عنوان التوصيل</div>
                <div class="font-bold max-w-xs truncate"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
            </div>
        </div>

        <!-- جدول المنتجات -->
        <div class="glass-panel rounded-2xl overflow-hidden mb-8">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-800/80 text-slate-300">
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
                                <div class="w-16 h-16 rounded-lg bg-slate-800 overflow-hidden border border-slate-700 flex-shrink-0">
                                    <?php if($item['image']): ?>
                                        <img src="../<?php echo $item['image']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full text-slate-500"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-base mb-1"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="text-xs text-slate-400 bg-slate-800 px-2 py-1 rounded inline-block">
                                        من متجر: <?php echo htmlspecialchars($item['store_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-center">
                            <span class="font-bold">x <?php echo $item['quantity']; ?></span>
                        </td>
                        <td class="p-4 text-center text-slate-300">
                            <?php echo number_format($item['price']); ?>
                        </td>
                        <td class="p-4 text-center font-bold text-brand-gold">
                            <?php echo number_format($item['price'] * $item['quantity']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-800/50 border-t border-slate-700">
                    <tr>
                        <td colspan="3" class="p-6 text-left text-lg font-bold">الإجمالي النهائي</td>
                        <td class="p-6 text-center text-2xl font-black text-brand-gold">
                            <?php echo number_format($order['total_amount']); ?> <span class="text-sm font-normal text-white">ر.ي</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="text-center text-slate-500 text-sm no-print">
            لديك استفسار حول هذا الطلب؟ <a href="https://wa.me/967770000000" class="text-brand-gold hover:underline">تواصل مع خدمة العملاء</a>
        </div>

    </div>

</body>
</html>