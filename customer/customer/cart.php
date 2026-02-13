<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "alsaqrmall_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // إنشاء السلة في الجلسة إذا لم تكن موجودة
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // معالجة الإجراءات (إضافة، حذف، تحديث)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // إضافة منتج للسلة
        if (isset($_POST['action']) && $_POST['action'] == 'add') {
            $product_id = $_POST['product_id'];
            $quantity = (int)$_POST['quantity'];
            
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = $quantity;
            }
        }
        
        // حذف منتج من السلة
        if (isset($_POST['action']) && $_POST['action'] == 'remove') {
            $product_id = $_POST['product_id'];
            unset($_SESSION['cart'][$product_id]);
        }

        // تحديث الكمية
        if (isset($_POST['action']) && $_POST['action'] == 'update') {
            $product_id = $_POST['product_id'];
            $quantity = (int)$_POST['quantity'];
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id] = $quantity;
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
        }
    }

    // جلب تفاصيل المنتجات الموجودة في السلة من قاعدة البيانات
    $cart_products = [];
    $total_price = 0;
    
    if (!empty($_SESSION['cart'])) {
        // تحويل مفاتيح السلة (أرقام المنتجات) إلى نص للاستعلام
        $ids = implode(',', array_keys($_SESSION['cart']));
        
        // جلب المنتجات مع اسم التاجر
        $stmt = $conn->query("
            SELECT p.*, v.store_name 
            FROM products p 
            JOIN vendors v ON p.vendor_id = v.id 
            WHERE p.id IN ($ids)
        ");
        $products_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products_db as $prod) {
            $prod['cart_qty'] = $_SESSION['cart'][$prod['id']];
            // التأكد من عدم تجاوز المخزون المتوفر
            if ($prod['cart_qty'] > $prod['stock']) {
                $prod['cart_qty'] = $prod['stock'];
                $_SESSION['cart'][$prod['id']] = $prod['stock']; // تحديث السلة بالحد الأقصى
            }
            $prod['subtotal'] = $prod['price'] * $prod['cart_qty'];
            $total_price += $prod['subtotal'];
            $cart_products[] = $prod;
        }
    }

} catch(PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سلة المشتريات | الصقر مول</title>
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
<body class="font-sans min-h-screen flex flex-col">

    <!-- ناف بار بسيط -->
    <nav class="bg-slate-900 border-b border-white/5 py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="../index.php" class="text-xl font-bold flex items-center gap-2 hover:text-brand-gold transition">
                <i class="fas fa-arrow-right"></i> متابعة التسوق
            </a>
            <div class="font-bold text-lg">سلة المشتريات</div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 flex-1">
        
        <?php if(empty($cart_products)): ?>
            <div class="text-center py-20">
                <i class="fas fa-shopping-cart text-6xl text-slate-600 mb-6"></i>
                <h2 class="text-2xl font-bold mb-4">السلة فارغة!</h2>
                <p class="text-slate-400 mb-8">لم تضف أي منتجات للسلة بعد.</p>
                <a href="../index.php" class="bg-brand-gold text-brand-dark px-8 py-3 rounded-xl font-bold hover:bg-yellow-500 transition">تصفح المنتجات</a>
            </div>
        <?php else: ?>
            <div class="flex flex-col lg:flex-row gap-8">
                
                <!-- قائمة المنتجات -->
                <div class="flex-1 space-y-4">
                    <h1 class="text-2xl font-bold mb-4">لديك <?php echo count($cart_products); ?> منتجات في السلة</h1>
                    
                    <?php foreach($cart_products as $item): ?>
                    <div class="glass-panel p-4 rounded-2xl flex flex-col sm:flex-row items-center gap-4 group hover:border-brand-gold/30 transition-all">
                        <!-- الصورة -->
                        <div class="w-24 h-24 bg-slate-800 rounded-xl overflow-hidden flex-shrink-0 border border-slate-700">
                            <?php if($item['image']): ?>
                                <img src="../<?php echo htmlspecialchars($item['image']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-500"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- التفاصيل -->
                        <div class="flex-1 text-center sm:text-right w-full">
                            <h3 class="font-bold text-lg mb-1"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="text-sm text-slate-400 mb-2 bg-slate-800/50 inline-block px-2 py-1 rounded">
                                متجر: <span class="text-brand-gold"><?php echo htmlspecialchars($item['store_name']); ?></span>
                            </div>
                            <div class="font-bold text-brand-gold text-lg block sm:hidden"><?php echo number_format($item['price']); ?> ر.ي</div>
                        </div>

                        <!-- السعر (لشاشات الكمبيوتر) -->
                        <div class="hidden sm:block text-center px-4">
                            <div class="text-xs text-slate-400 mb-1">السعر</div>
                            <div class="font-bold"><?php echo number_format($item['price']); ?></div>
                        </div>

                        <!-- التحكم بالكمية -->
                        <form method="POST" class="flex items-center gap-2 bg-slate-800 rounded-lg p-1 border border-slate-700">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            
                            <button type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown(); this.parentNode.submit();" class="w-8 h-8 flex items-center justify-center hover:bg-slate-700 rounded text-slate-300 transition-colors">-</button>
                            
                            <input type="number" name="quantity" value="<?php echo $item['cart_qty']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="w-12 text-center bg-transparent border-none focus:ring-0 font-bold p-0 text-white" onchange="this.form.submit()">
                            
                            <button type="button" onclick="this.parentNode.querySelector('input[type=number]').stepUp(); this.parentNode.submit();" class="w-8 h-8 flex items-center justify-center hover:bg-slate-700 rounded text-slate-300 transition-colors">+</button>
                        </form>

                        <!-- المجموع الفرعي والحذف -->
                        <div class="text-right min-w-[100px] flex flex-col items-end gap-2">
                            <div class="font-bold text-brand-gold text-lg"><?php echo number_format($item['subtotal']); ?> ر.ي</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="text-red-500 hover:text-red-400 text-sm flex items-center gap-1 bg-red-500/10 px-2 py-1 rounded hover:bg-red-500/20 transition-all">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ملخص الطلب -->
                <div class="lg:w-96">
                    <div class="glass-panel p-6 rounded-2xl sticky top-8">
                        <h3 class="font-bold text-xl mb-6 pb-4 border-b border-slate-700">ملخص الطلب</h3>
                        
                        <div class="flex justify-between mb-4 text-slate-300">
                            <span>المجموع الفرعي</span>
                            <span><?php echo number_format($total_price); ?> ر.ي</span>
                        </div>
                        
                        <div class="flex justify-between mb-6 text-green-400 text-sm">
                            <span><i class="fas fa-truck ml-1"></i> التوصيل</span>
                            <span>يحدد عند الدفع</span>
                        </div>
                        
                        <div class="border-t border-slate-700 my-4"></div>

                        <div class="flex justify-between mb-8 text-2xl font-bold text-brand-gold">
                            <span>الإجمالي</span>
                            <span><?php echo number_format($total_price); ?> <span class="text-sm font-normal text-slate-400">ر.ي</span></span>
                        </div>

                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="checkout.php" class="block w-full bg-gradient-to-r from-brand-gold to-yellow-600 text-brand-dark font-black py-4 rounded-xl text-center shadow-lg shadow-yellow-500/20 transition-transform hover:-translate-y-1">
                                إتمام الشراء <i class="fas fa-arrow-left mr-1"></i>
                            </a>
                        <?php else: ?>
                            <a href="../login.php?redirect=checkout" class="block w-full bg-slate-700 hover:bg-slate-600 text-white font-bold py-4 rounded-xl text-center transition border border-slate-600 hover:border-slate-500">
                                سجل دخول لإتمام الشراء
                            </a>
                            <p class="text-xs text-center text-slate-500 mt-2">يجب عليك تسجيل الدخول للمتابعة</p>
                        <?php endif; ?>
                        
                        <div class="mt-6 flex justify-center gap-4 text-slate-500 text-2xl">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>
        
    </div>

</body>
</html>