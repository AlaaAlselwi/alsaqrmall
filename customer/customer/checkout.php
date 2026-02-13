<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "alsaqrmall_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        // توجيه لصفحة الدخول مع حفظ الرابط للعودة له بعد الدخول
        header("Location: ../login.php?redirect=checkout");
        exit();
    }

    // 2. التحقق من السلة
    if (empty($_SESSION['cart'])) {
        header("Location: cart.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $msg = "";

    // 3. جلب منتجات السلة وحساب الإجمالي
    $ids = implode(',', array_keys($_SESSION['cart']));
    $stmt = $conn->query("
        SELECT p.*, v.store_name, v.id as vendor_id 
        FROM products p 
        JOIN vendors v ON p.vendor_id = v.id 
        WHERE p.id IN ($ids)
    ");
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_amount = 0;
    $involved_vendors = []; // مصفوفة لتخزين معرفات التجار المشاركين في الطلب

    foreach ($cart_items as $k => $item) {
        $qty = $_SESSION['cart'][$item['id']];
        $cart_items[$k]['qty'] = $qty;
        $cart_items[$k]['subtotal'] = $item['price'] * $qty;
        $total_amount += $cart_items[$k]['subtotal'];
        
        // تجميع التجار لجلب طرق دفعهم
        if (!in_array($item['vendor_id'], $involved_vendors)) {
            $involved_vendors[] = $item['vendor_id'];
        }
    }

    // 4. جلب طرق الدفع الخاصة بالتجار المشاركين في السلة
    $vendor_ids_str = implode(',', $involved_vendors);
    $payment_methods = [];
    if (!empty($vendor_ids_str)) {
        $pm_stmt = $conn->query("
            SELECT pm.*, v.store_name 
            FROM vendor_payment_methods pm
            JOIN vendors v ON pm.vendor_id = v.id
            WHERE pm.vendor_id IN ($vendor_ids_str)
        ");
        $payment_methods = $pm_stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
        // التجميع حسب اسم المتجر: ['Store Name' => [method1, method2]]
    }

    // 5. معالجة إرسال الطلب (Submit Order)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $address = htmlspecialchars($_POST['address']);
        $payment_type = $_POST['payment_method']; // cod or wallet

        // رفع صورة السند (إذا وجد)
        $receipt_path = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $new_name = "receipt_" . time() . ".$ext";
            if (!is_dir("../uploads/receipts")) mkdir("../uploads/receipts", 0777, true);
            move_uploaded_file($_FILES['receipt']['tmp_name'], "../uploads/receipts/" . $new_name);
            $receipt_path = "uploads/receipts/" . $new_name;
        }

        $conn->beginTransaction();

        try {
            // أ. إنشاء السجل الرئيسي في جدول orders
            // ملاحظة: في الأنظمة الكبيرة ننشئ طلب منفصل لكل تاجر (Split Order). 
            // للتبسيط هنا: سننشئ طلباً واحداً يجمع كل شيء.
            $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, status, payment_method, delivery_address) VALUES (?, ?, 'pending', ?, ?)");
            $stmt->execute([$user_id, $total_amount, $payment_type, $address]);
            $order_id = $conn->lastInsertId();

            // ب. إدخال العناصر في order_items
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, vendor_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($cart_items as $item) {
                $item_stmt->execute([
                    $order_id,
                    $item['id'],
                    $item['vendor_id'],
                    $item['qty'],
                    $item['price']
                ]);

                // إنقاص المخزون
                $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$item['qty'], $item['id']]);
            }

            // ج. تفريغ السلة وإنهاء العملية
            unset($_SESSION['cart']);
            $conn->commit();
            
            // توجيه لصفحة النجاح
            header("Location: orders.php?success=1");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $msg = "حدث خطأ أثناء المعالجة: " . $e->getMessage();
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
    <title>إتمام الشراء | الصقر مول</title>
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
        .input-field {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .input-field:focus { border-color: #fbbf24; outline: none; }
    </style>
</head>
<body class="font-sans min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        
        <div class="flex items-center gap-4 mb-8">
            <a href="cart.php" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center hover:bg-slate-700 transition">
                <i class="fas fa-arrow-right"></i>
            </a>
            <h1 class="text-3xl font-bold">إتمام الطلب</h1>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-200 p-4 rounded-xl mb-6 text-center">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- العمود الأيمن: بيانات الشحن والدفع -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- 1. عنوان التوصيل -->
                <div class="glass-panel p-6 rounded-2xl">
                    <h3 class="font-bold text-xl mb-4 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-brand-gold text-brand-dark flex items-center justify-center text-sm">1</span>
                        عنوان التوصيل
                    </h3>
                    <div>
                        <label class="block text-slate-400 mb-2 text-sm">العنوان التفصيلي (المدينة، الشارع، معلم قريب)</label>
                        <textarea name="address" required rows="3" class="w-full rounded-xl p-3 input-field" placeholder="مثال: صنعاء، شارع حدة، جوار مطعم ريمان..."></textarea>
                    </div>
                </div>

                <!-- 2. طريقة الدفع -->
                <div class="glass-panel p-6 rounded-2xl">
                    <h3 class="font-bold text-xl mb-4 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-brand-gold text-brand-dark flex items-center justify-center text-sm">2</span>
                        طريقة الدفع
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="cod" class="peer sr-only" checked onchange="togglePaymentDetails('cod')">
                            <div class="p-4 rounded-xl border border-slate-600 peer-checked:border-brand-gold peer-checked:bg-brand-gold/10 transition-all flex items-center gap-3">
                                <i class="fas fa-money-bill-wave text-2xl text-green-400"></i>
                                <div>
                                    <div class="font-bold">دفع عند الاستلام</div>
                                    <div class="text-xs text-slate-400">ادفع نقداً عند وصول المندوب</div>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="wallet" class="peer sr-only" onchange="togglePaymentDetails('wallet')">
                            <div class="p-4 rounded-xl border border-slate-600 peer-checked:border-brand-gold peer-checked:bg-brand-gold/10 transition-all flex items-center gap-3">
                                <i class="fas fa-wallet text-2xl text-blue-400"></i>
                                <div>
                                    <div class="font-bold">تحويل بنكي / محفظة</div>
                                    <div class="text-xs text-slate-400">الكريمي، ون كاش، جوالي...</div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- تفاصيل التحويل (تظهر فقط عند اختيار المحفظة) -->
                    <div id="wallet-details" class="hidden space-y-6 border-t border-slate-700 pt-6">
                        <div class="bg-yellow-500/10 border border-yellow-500/30 p-4 rounded-xl text-sm text-yellow-200 mb-4">
                            <i class="fas fa-info-circle ml-1"></i> يرجى تحويل إجمالي المبلغ إلى أحد الحسابات التالية، ثم إرفاق صورة السند.
                        </div>

                        <?php if(!empty($payment_methods)): ?>
                            <div class="space-y-4">
                                <?php foreach($payment_methods as $store => $methods): ?>
                                    <div class="bg-slate-800/50 p-4 rounded-xl">
                                        <h4 class="font-bold text-slate-300 mb-2 border-b border-slate-700 pb-1 text-sm">حسابات متجر: <span class="text-white"><?php echo htmlspecialchars($store); ?></span></h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <?php foreach($methods as $method): ?>
                                                <div class="flex justify-between items-center bg-slate-900 p-2 rounded-lg text-sm">
                                                    <span class="text-slate-400"><?php echo htmlspecialchars($method['provider_name']); ?></span>
                                                    <span class="font-mono font-bold text-brand-gold select-all"><?php echo htmlspecialchars($method['account_number']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-slate-500 py-4">لم يقم التجار بإضافة حسابات بنكية بعد. يرجى اختيار الدفع عند الاستلام.</div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-slate-400 mb-2 text-sm">صورة سند التحويل (اختياري)</label>
                            <input type="file" name="receipt" class="block w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer">
                        </div>
                    </div>
                </div>

            </div>

            <!-- العمود الأيسر: ملخص الطلب -->
            <div class="lg:col-span-1">
                <div class="glass-panel p-6 rounded-2xl sticky top-8">
                    <h3 class="font-bold text-xl mb-6 pb-4 border-b border-slate-700">ملخص الفاتورة</h3>
                    
                    <div class="space-y-3 mb-6 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach($cart_items as $item): ?>
                        <div class="flex justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-700 text-xs w-5 h-5 flex items-center justify-center rounded text-slate-300"><?php echo $item['qty']; ?></span>
                                <span class="text-slate-300 truncate max-w-[150px]"><?php echo htmlspecialchars($item['name']); ?></span>
                            </div>
                            <span class="font-bold"><?php echo number_format($item['subtotal']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-slate-700 pt-4 space-y-2">
                        <div class="flex justify-between text-slate-400">
                            <span>المجموع</span>
                            <span><?php echo number_format($total_amount); ?> ر.ي</span>
                        </div>
                        <div class="flex justify-between text-green-400">
                            <span>التوصيل</span>
                            <span>مجاني</span>
                        </div>
                    </div>

                    <div class="flex justify-between mt-6 pt-4 border-t border-slate-700 text-xl font-bold text-brand-gold">
                        <span>الإجمالي النهائي</span>
                        <span><?php echo number_format($total_amount); ?> ر.ي</span>
                    </div>

                    <button type="submit" class="w-full mt-8 bg-gradient-to-r from-brand-gold to-yellow-600 text-brand-dark font-black py-4 rounded-xl shadow-lg hover:shadow-yellow-500/20 transform hover:-translate-y-1 transition-all">
                        تأكيد الطلب <i class="fas fa-check-circle mr-2"></i>
                    </button>

                    <p class="text-center text-xs text-slate-500 mt-4">
                        بالضغط على تأكيد الطلب، فإنك توافق على شروط وأحكام الموقع.
                    </p>
                </div>
            </div>

        </form>
    </div>

    <script>
        function togglePaymentDetails(method) {
            const details = document.getElementById('wallet-details');
            if (method === 'wallet') {
                details.classList.remove('hidden');
            } else {
                details.classList.add('hidden');
            }
        }
    </script>
</body>
</html>