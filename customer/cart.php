<?php
$page_title = 'سلة المشتريات | الصقر مول';
require_once 'includes/header.php';

// تهيئة السلة إذا لم تكن موجودة
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$msg = "";
$msg_type = "";

// معالجة طلبات السلة (إضافة/تحديث/حذف)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' && isset($_POST['product_id'])) {
            $pid = $_POST['product_id'];
            $qty = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
            
            // إذا المنتج موجود مسبقاً، نزيد الكمية
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid] += $qty;
            } else {
                $_SESSION['cart'][$pid] = $qty;
            }
            $msg = "تمت إضافة المنتج للسلة بنجاح.";
            $msg_type = "success";
        }
        
        elseif ($action === 'update' && isset($_POST['product_id']) && isset($_POST['quantity'])) {
            $pid = $_POST['product_id'];
            $qty = max(1, intval($_POST['quantity']));
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid] = $qty;
                $msg = "تم تحديث الكمية.";
                $msg_type = "success";
            }
        }
        
        elseif ($action === 'remove' && isset($_POST['product_id'])) {
            $pid = $_POST['product_id'];
            if (isset($_SESSION['cart'][$pid])) {
                unset($_SESSION['cart'][$pid]);
                $msg = "تم حذف المنتج من السلة.";
                $msg_type = "warning";
            }
        }
    }
}

// جلب تفاصيل المنتجات من قاعدة البيانات
$cart_items = [];
$total_price = 0;

if (!empty($_SESSION['cart'])) {
    try {
        $db = Database::connect();
        $productsCollection = $db->products;
        
        // تحويل مفاتيح السلة (ID النصوص) إلى ObjectId
        $ids = [];
        foreach (array_keys($_SESSION['cart']) as $id_str) {
            try {
                $ids[] = new MongoDB\BSON\ObjectId($id_str);
            } catch (Exception $e) { continue; }
        }

        if (!empty($ids)) {
            // جلب المنتجات
            $cursor = $productsCollection->find(['_id' => ['$in' => $ids]]);
            
            foreach ($cursor as $prod) {
                $item_id = (string)$prod['_id'];
                // نتأكد أن المنتج لا يزال في السلة (احتياط)
                if (isset($_SESSION['cart'][$item_id])) {
                    $qty = $_SESSION['cart'][$item_id];
                    // نتأكد أن الكمية لا تتجاوز المخزون
                    if ($qty > $prod['stock']) {
                        $qty = $prod['stock'];
                        $_SESSION['cart'][$item_id] = $qty; // تحديث السلة بالحد الأقصى المتاح
                    }

                    $line_total = $prod['price'] * $qty;
                    $total_price += $line_total;

                    $cart_items[] = [
                        'id' => $item_id,
                        'name' => $prod['name'],
                        'price' => $prod['price'],
                        'image' => $prod['image'],
                        'stock' => $prod['stock'],
                        'qty' => $qty,
                        'line_total' => $line_total
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $msg = "حدث خطأ في جلب بيانات السلة: " . $e->getMessage();
        $msg_type = "error";
    }
}
?>

<div class="h-24"></div>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-black mb-8 flex items-center gap-3">
        <i class="fas fa-shopping-cart text-brand-gold"></i> سلة المشتريات
    </h1>

    <?php if(!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-xl font-bold <?php echo $msg_type == 'success' ? 'bg-green-500/20 text-green-500' : ($msg_type == 'warning' ? 'bg-yellow-500/20 text-yellow-500' : 'bg-red-500/20 text-red-500'); ?>">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <?php if(count($cart_items) > 0): ?>
    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- قائمة المنتجات -->
        <div class="lg:w-2/3 space-y-4">
            <?php foreach($cart_items as $item): ?>
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-4 flex flex-col md:flex-row items-center gap-6" data-aos="fade-up">
                <!-- صورة المنتج -->
                <div class="w-24 h-24 bg-slate-700 rounded-xl overflow-hidden shrink-0">
                    <img src="../<?php echo htmlspecialchars($item['image']); ?>" class="w-full h-full object-cover">
                </div>

                <!-- تفاصيل -->
                <div class="flex-1 text-center md:text-right">
                    <h3 class="font-bold text-lg text-white mb-1"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <div class="text-brand-gold font-bold"><?php echo number_format($item['price']); ?> ر.ي</div>
                </div>

                <!-- الكمية -->
                <div class="flex items-center gap-3">
                    <form method="POST" class="flex items-center">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                        <input type="number" name="quantity" value="<?php echo $item['qty']; ?>" min="1" max="<?php echo $item['stock']; ?>" onchange="this.form.submit()" 
                            class="w-16 bg-slate-900 border border-slate-600 rounded-lg text-center py-2 focus:border-brand-gold outline-none">
                    </form>
                </div>

                <!-- الإجمالي والحذف -->
                <div class="flex flex-col items-end gap-2 shrink-0">
                    <div class="font-bold text-lg"><?php echo number_format($item['line_total']); ?> ر.ي</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="text-red-400 hover:text-red-500 text-sm flex items-center gap-1 transition-colors">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ملخص الطلب -->
        <div class="lg:w-1/3">
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 sticky top-28" data-aos="fade-left">
                <h3 class="text-xl font-bold mb-6">ملخص الطلب</h3>
                
                <div class="space-y-4 mb-6 text-sm">
                    <div class="flex justify-between text-slate-400">
                        <span>المجموع الفرعي</span>
                        <span><?php echo number_format($total_price); ?> ر.ي</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>الضريبة (0%)</span>
                        <span>0 ر.ي</span>
                    </div>
                    <div class="border-t border-slate-700 pt-4 flex justify-between font-bold text-lg text-white">
                        <span>الإجمالي الكلي</span>
                        <span class="text-brand-gold"><?php echo number_format($total_price); ?> ر.ي</span>
                    </div>
                </div>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="checkout.php" class="block w-full text-center bg-brand-gold hover:bg-yellow-500 text-brand-dark font-black py-4 rounded-xl shadow-lg shadow-yellow-500/20 transition-all transform hover:-translate-y-1">
                        تأكيد الطلب والدفع <i class="fas fa-arrow-left mr-2"></i>
                    </a>
                <?php else: ?>
                    <a href="../login.php?redirect=customer/cart.php" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-all">
                        سجل دخولك للإتمام <i class="fas fa-sign-in-alt mr-2"></i>
                    </a>
                <?php endif; ?>
                
                <div class="mt-6 flex justify-center gap-4 text-slate-500">
                    <i class="fab fa-cc-visa text-2xl"></i>
                    <i class="fab fa-cc-mastercard text-2xl"></i>
                    <i class="fas fa-money-bill-wave text-2xl"></i>
                </div>
            </div>
        </div>

    </div>
    <?php else: ?>
        <div class="text-center py-20 bg-slate-800/30 rounded-3xl border border-slate-700/50">
            <i class="fas fa-shopping-cart text-7xl text-slate-600 mb-6 animate-bounce"></i>
            <h2 class="text-2xl font-bold mb-2">السلة فارغة!</h2>
            <p class="text-slate-400 mb-8 max-w-md mx-auto">يبدو أنك لم تضف أي منتجات بعد. تصفح المتجر واكتشف أفضل العروض.</p>
            <a href="index.php" class="inline-block bg-brand-gold text-brand-dark font-bold py-3 px-8 rounded-full hover:bg-white transition-all transform hover:scale-105 shadow-lg">
                تصفح المنتجات
            </a>
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
