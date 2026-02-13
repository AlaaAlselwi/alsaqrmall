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

    $customer_id = $_SESSION['user_id'];

    // 2. جلب طلبات الزبون
    $stmt = $conn->prepare("
        SELECT * FROM orders 
        WHERE customer_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي | الصقر مول</title>
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
    </style>
</head>
<body class="font-sans min-h-screen">

    <!-- ناف بار بسيط -->
    <nav class="bg-slate-900 border-b border-white/5 py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="../index.php" class="text-xl font-bold flex items-center gap-2 hover:text-brand-gold transition">
                <i class="fas fa-home"></i> الرئيسية
            </a>
            <div class="font-bold text-lg text-brand-gold">سجل طلباتي</div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-5xl">

        <!-- رسالة نجاح الطلب (تظهر فقط بعد التحويل من صفحة الدفع) -->
        <?php if(isset($_GET['success'])): ?>
        <div class="bg-green-600/20 border border-green-500/50 text-green-400 p-6 rounded-2xl mb-8 text-center animate-pulse">
            <i class="fas fa-check-circle text-4xl mb-2"></i>
            <h2 class="text-2xl font-bold">شكراً لطلبك!</h2>
            <p>تم استلام طلبك بنجاح وسيتم مراجعته وتجهيزه قريباً.</p>
        </div>
        <?php endif; ?>

        <h1 class="text-3xl font-bold mb-8 flex items-center gap-3">
            <i class="fas fa-box-open text-brand-gold"></i> أرشيف الطلبات
        </h1>

        <?php if(empty($orders)): ?>
            <div class="text-center py-20 glass-panel rounded-2xl">
                <i class="fas fa-history text-6xl text-slate-600 mb-6"></i>
                <h3 class="text-2xl font-bold mb-2">ليس لديك طلبات سابقة</h3>
                <p class="text-slate-400 mb-8">ابدأ التسوق الآن واكتشف أفضل العروض.</p>
                <a href="../index.php" class="bg-brand-gold text-brand-dark px-8 py-3 rounded-xl font-bold hover:bg-yellow-500 transition">تسوق الآن</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach($orders as $order): ?>
                <div class="glass-panel p-6 rounded-2xl hover:border-brand-gold/30 transition-all group">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        
                        <!-- معلومات الطلب -->
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="bg-slate-700 text-white px-3 py-1 rounded-lg font-mono text-sm">#<?php echo $order['id']; ?></span>
                                <span class="text-slate-400 text-sm"><i class="far fa-calendar-alt ml-1"></i> <?php echo date('Y/m/d h:i A', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-300">الإجمالي:</span>
                                <span class="text-xl font-bold text-brand-gold"><?php echo number_format($order['total_amount']); ?> ر.ي</span>
                            </div>
                        </div>

                        <!-- الحالة -->
                        <div class="flex items-center gap-4">
                            <?php 
                                $status_config = [
                                    'pending' => ['bg' => 'bg-yellow-500/10', 'text' => 'text-yellow-500', 'label' => 'قيد المراجعة', 'icon' => 'fa-clock'],
                                    'processing' => ['bg' => 'bg-blue-500/10', 'text' => 'text-blue-500', 'label' => 'جاري التجهيز', 'icon' => 'fa-cog fa-spin'],
                                    'shipped' => ['bg' => 'bg-purple-500/10', 'text' => 'text-purple-500', 'label' => 'تم الشحن', 'icon' => 'fa-shipping-fast'],
                                    'delivered' => ['bg' => 'bg-green-500/10', 'text' => 'text-green-500', 'label' => 'تم التسليم', 'icon' => 'fa-check-circle'],
                                    'cancelled' => ['bg' => 'bg-red-500/10', 'text' => 'text-red-500', 'label' => 'ملغي', 'icon' => 'fa-times-circle'],
                                ];
                                $st = $status_config[$order['status']] ?? $status_config['pending'];
                            ?>
                            <div class="<?php echo $st['bg'] . ' ' . $st['text']; ?> px-4 py-2 rounded-xl flex items-center gap-2 font-bold text-sm">
                                <i class="fas <?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
                            </div>

                            <!-- زر التفاصيل -->
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center hover:bg-white hover:text-brand-dark transition-colors" title="عرض التفاصيل">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>