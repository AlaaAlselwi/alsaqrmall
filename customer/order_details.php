<?php
$page_title = 'تفاصيل الطلب | الصقر مول';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='../login.php';</script>";
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $ordersCollection = $db->orders;
    $productsCollection = $db->products;

    if (!isset($_GET['id'])) {
        header("Location: profile.php");
        exit();
    }

    $order_id = new MongoDB\BSON\ObjectId($_GET['id']);
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

    // معالجة طلب الإلغاء
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
        $order = $ordersCollection->findOne(['_id' => $order_id, 'customer_id' => $user_id]);
        
        if ($order && $order['status'] === 'pending') {
            // 1. استعادة المخزون
            foreach ($order['items'] as $item) {
                $productsCollection->updateOne(
                    ['_id' => $item['product_id']],
                    ['$inc' => ['stock' => (int)$item['quantity']]]
                );
            }

            // 2. تحديث الحالة
            $ordersCollection->updateOne(
                ['_id' => $order_id],
                ['$set' => ['status' => 'cancelled']]
            );

            $msg = "تم إلغاء الطلب بنجاح واستعادة المخزون.";
            $msg_type = "success";
        } else {
            $msg = "عذراً، لا يمكن إلغاء هذا الطلب (قد يكون قيد التجهيز أو تم شحنه).";
            $msg_type = "error";
        }
    }

    // جلب تفاصيل الطلب مع بيانات التاجر
    $order = $ordersCollection->findOne(['_id' => $order_id, 'customer_id' => $user_id]);
    
    // جلب بيانات التاجر
    $vendor = $db->vendors->findOne(['user_id' => $order['vendor_id']]);

    if (!$order) {
        die('<div class="container mx-auto py-20 text-center">الطلب غير موجود.</div>');
    }

} catch (Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<div class="h-24"></div>

<div class="container mx-auto px-4 py-8">
    
    <div class="mb-6 flex items-center gap-4">
        <a href="profile.php" class="w-10 h-10 rounded-full bg-slate-800 hover:bg-slate-700 flex items-center justify-center text-white transition-colors">
            <i class="fas fa-arrow-right"></i>
        </a>
        <h1 class="text-2xl font-bold">تفاصيل الطلب #<?php echo substr((string)$order['_id'], -6); ?></h1>
    </div>

    <?php if(!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-xl font-bold <?php echo $msg_type == 'success' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500'; ?>">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- القسم الرئيسي: المنتجات والتتبع -->
        <div class="md:col-span-2 space-y-6">
            
            <!-- تتبع الطلب -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-6 flex items-center gap-2">
                    <i class="fas fa-truck-fast text-brand-gold"></i> تتبع الشحنة
                </h3>

                <!-- Timeline -->
                <div class="relative px-4">
                    <?php 
                        $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                        $current_status = $order['status'];
                        $is_cancelled = ($current_status === 'cancelled');
                        
                        $status_labels = [
                            'pending' => 'تم الطلب',
                            'processing' => 'جاري التجهيز',
                            'shipped' => 'تم الشحن',
                            'delivered' => 'تم التسليم'
                        ];

                        $current_idx = array_search($current_status, $statuses);
                        if ($current_idx === false && !$is_cancelled) $current_idx = -1;
                    ?>

                    <?php if($is_cancelled): ?>
                        <div class="bg-red-500/10 text-red-500 p-4 rounded-xl text-center border border-red-500/20">
                            <i class="fas fa-times-circle text-2xl mb-2"></i>
                            <div class="font-bold">هذا الطلب ملغي</div>
                        </div>
                    <?php else: ?>
                        <!-- Progress Bar Background -->
                        <div class="absolute top-4 right-8 left-8 h-1 bg-slate-700 z-0"></div>
                        
                        <!-- Progress Bar Active -->
                        <div class="absolute top-4 right-8 h-1 bg-brand-gold z-0 transition-all duration-1000" style="width: <?php echo ($current_idx / (count($statuses)-1)) * 100; ?>%"></div>

                        <div class="relative z-10 flex justify-between">
                            <?php foreach ($statuses as $idx => $status): ?>
                                <div class="flex flex-col items-center gap-2 w-20">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-4 
                                        <?php 
                                            if ($idx <= $current_idx) echo 'bg-brand-gold border-slate-800 text-brand-dark';
                                            else echo 'bg-slate-800 border-slate-700 text-slate-500';
                                        ?>">
                                        <?php if ($idx <= $current_idx) echo '<i class="fas fa-check"></i>'; else echo $idx + 1; ?>
                                    </div>
                                    <span class="text-xs font-bold <?php echo $idx <= $current_idx ? 'text-white' : 'text-slate-500'; ?>">
                                        <?php echo $status_labels[$status]; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- قائمة المنتجات -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-6 flex items-center gap-2">
                    <i class="fas fa-box-open text-brand-gold"></i> محتويات الطلب (متجر: <?php echo htmlspecialchars($vendor['store_name']); ?>)
                </h3>
                
                <div class="space-y-4">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="flex items-center gap-4 bg-slate-900/50 p-4 rounded-xl">
                        <!-- صورة افتراضية أو صورة المنتج (إذا كانت مخزنة في item، وإلا نحتاج لجلبها من المنتجات، 
                             لكن للتبسيط سنستخدم أيقونة أو صورة افتراضية) -->
                        <div class="w-16 h-16 bg-slate-700 rounded-lg flex items-center justify-center text-2xl text-slate-500">
                            <i class="fas fa-cube"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="text-sm text-slate-400">الكمية: <?php echo $item['quantity']; ?></div>
                        </div>
                        <div class="text-brand-gold font-bold">
                            <?php echo number_format($item['line_total']); ?> ر.ي
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- القائمة الجانبية: الملخص والإجراءات -->
        <div class="space-y-6">
            
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-4 text-brand-gold">ملخص الدفع</h3>
                
                <div class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>طريقة الدفع</span>
                        <div class="text-left">
                            <span class="text-white font-bold block">
                                <?php 
                                    echo $order['payment_method'] === 'transfer' ? 'تحويل بنكي' : 'عند الاستلام';
                                ?>
                            </span>
                            <?php if($order['payment_method'] === 'transfer' && !empty($order['payment_receipt'])): ?>
                                <a href="../<?php echo $order['payment_receipt']; ?>" target="_blank" class="text-xs text-brand-accent hover:text-white underline mt-1 block">
                                    <i class="fas fa-image"></i> عرض السند المرفق
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm text-slate-300">
                        <span>الحالة</span>
                        <span class="px-2 py-0.5 rounded text-xs 
                            <?php 
                                if($order['status']=='pending') echo 'bg-yellow-500/20 text-yellow-500';
                                elseif($order['status']=='delivered') echo 'bg-green-500/20 text-green-500';
                                elseif($order['status']=='cancelled') echo 'bg-red-500/20 text-red-500';
                                else echo 'bg-blue-500/20 text-blue-500';
                            ?>">
                            <?php echo $status_labels[$order['status']] ?? $order['status']; ?>
                        </span>
                    </div>
                    <div class="flex justify-between text-xl font-bold text-white pt-4 border-t border-slate-700">
                        <span>الإجمالي</span>
                        <span class="text-brand-gold"><?php echo number_format($order['total_amount']); ?> ر.ي</span>
                    </div>
                </div>

                <?php if($order['status'] === 'pending'): ?>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء الطلب؟ سيتم استعادة المخزون.')">
                        <button type="submit" name="cancel_order" class="w-full bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white border border-red-500/20 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-times"></i> إلغاء الطلب
                        </button>
                    </form>
                    <p class="text-xs text-slate-500 mt-3 text-center">يمكنك إلغاء الطلب فقط وهو في مرحلة "قيد الانتظار".</p>
                <?php endif; ?>

            </div>

            <!-- عنوان التوصيل -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-4 text-brand-gold">عنوان التوصيل</h3>
                <div class="text-sm text-slate-300 space-y-2">
                    <p><i class="fas fa-map-marker-alt w-5"></i> <?php echo htmlspecialchars($order['shipping_address']['city'] . ' - ' . $order['shipping_address']['street']); ?></p>
                    <p><i class="fas fa-phone w-5"></i> <?php echo htmlspecialchars($order['shipping_address']['phone']); ?></p>
                    <?php if(!empty($order['shipping_address']['details'])): ?>
                        <p><i class="fas fa-info-circle w-5"></i> <?php echo htmlspecialchars($order['shipping_address']['details']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
