<?php
$page_title = 'إتمام الطلب | الصقر مول';
require_once 'includes/header.php';

// حماية الصفحة: تسجيل الدخول مطلوب
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='../login.php?redirect=customer/checkout.php';</script>";
    exit();
}

// حماية الصفحة: السلة يجب أن لا تكون فارغة
if (empty($_SESSION['cart'])) {
    echo "<script>window.location.href='cart.php';</script>";
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $productsCollection = $db->products;
    $ordersCollection = $db->orders;
    $usersCollection = $db->users;
    
    // جلب بيانات المستخدم لاستخدامها في العنوان الافتراضي
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $user = $usersCollection->findOne(['_id' => $user_id]);

    // ---------------------------------------------------------
    // 1. تجهيز بيانات السلة للعرض (تجميع حسب التاجر)
    // ---------------------------------------------------------
    $cart_ids = [];
    foreach (array_keys($_SESSION['cart']) as $id_str) {
        try { $cart_ids[] = new MongoDB\BSON\ObjectId($id_str); } catch (Exception $e) {}
    }

    // جلب تفاصيل المنتجات والمتاجر المرتبطة بها
    $products_cursor = $productsCollection->aggregate([
        ['$match' => ['_id' => ['$in' => $cart_ids]]],
        ['$lookup' => [
            'from' => 'vendors',
            'localField' => 'vendor_id',
            'foreignField' => '_id', 
            'as' => 'vendor_data'
        ]],
        ['$unwind' => '$vendor_data'] // products MUST have a vendor
    ]);

    $cart_grouped = [];
    $grand_total = 0;

    foreach ($products_cursor as $prod) {
        $pid = (string)$prod['_id'];
        $qty = $_SESSION['cart'][$pid] ?? 0;
        
        if ($qty > 0) {
            $vid = (string)$prod['vendor_id'];
            
            if (!isset($cart_grouped[$vid])) {
                $cart_grouped[$vid] = [
                    'vendor_info' => $prod['vendor_data'],
                    'items' => [],
                    'subtotal' => 0
                ];
            }
            
            $line_total = $prod['price'] * $qty;
            $cart_grouped[$vid]['items'][] = [
                'product' => $prod,
                'qty' => $qty,
                'line_total' => $line_total
            ];
            $cart_grouped[$vid]['subtotal'] += $line_total;
            $grand_total += $line_total;
        }
    }

    // ---------------------------------------------------------
    // 2. معالجة الطلب عند الإرسال
    // ---------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
        
        // التحقق من الحقول الأساسية
        // ... (سيتم تحديث هذا الجزء لاحقاً لدعم رفع الملفات)
        
        // سنستخدم $cart_grouped التي بنيناها بالأعلى للتكرار وإنشاء الطلبات
        // لكن نحتاج التأكد من المخزون مرة أخرى لحظة الشراء
        
        $orders_created = 0;
        $upload_errors = [];

        foreach ($cart_grouped as $vid => $group) {
            // التحقق من المخزون
            foreach ($group['items'] as $item) {
                $db_prod = $productsCollection->findOne(['_id' => $item['product']['_id']]);
                if ($db_prod['stock'] < $item['qty']) {
                    throw new Exception("الكمية المطلوبة من '{$item['product']['name']}' غير متوفرة.");
                }
            }

            // معالجة الدفع (صورة السند)
            $payment_method = $_POST['payment_method_' . $vid] ?? 'cod';
            $receipt_path = null;
            $status = 'pending'; // انتظار الموافقة

            if ($payment_method === 'transfer') {
                if (!isset($_FILES['receipt_' . $vid]) || $_FILES['receipt_' . $vid]['error'] !== 0) {
                    if (isset($_FILES['receipt_' . $vid]) && $_FILES['receipt_' . $vid]['error'] === 4) {
                         throw new Exception("يرجى إرفاق صورة السند للمتجر: " . $group['vendor_info']['store_name']);
                    } else {
                         throw new Exception("حدث خطأ أثناء رفع ملف السند للمتجر: " . $group['vendor_info']['store_name'] . " (Error Code: " . $_FILES['receipt_' . $vid]['error'] . ")");
                    }
                }

                $file = $_FILES['receipt_' . $vid];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'heic']; // Added heic just in case, though browser support varies
                    
                if (!in_array($ext, $allowed)) {
                    throw new Exception("نسق الملف غير مدعوم. يرجى رفع صورة (JPG, PNG) أو ملف PDF.");
                }

                $new_name = "receipt_" . time() . "_" . uniqid() . "." . $ext;
                $target_dir = "../uploads/receipts/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                
                $destination = $target_dir . $new_name;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $receipt_path = "uploads/receipts/" . $new_name;
                } else {
                    throw new Exception("فشل نقل الملف إلى الخادم. يرجى المحاولة مرة أخرى.");
                }
            }

            // إنشاء الطلب
            $orderData = [
                'customer_id' => $user_id,
                'customer_name' => $user['first_name'] . ' ' . $user['last_name'],
                'vendor_id' => $group['vendor_info']['_id'], // استخدام _id الخاص بمستند التاجر (وليس user_id) ليتوافق مع استعلامات لوحة التحكم
                'items' => array_map(function($i) {
                    return [
                        'product_id' => $i['product']['_id'],
                        'product_name' => $i['product']['name'],
                        'price' => $i['product']['price'],
                        'quantity' => $i['qty'],
                        'line_total' => $i['line_total']
                    ];
                }, $group['items']),
                'total_amount' => $group['subtotal'],
                'status' => 'pending',
                'payment_method' => $payment_method,
                'payment_receipt' => $receipt_path,
                'shipping_address' => [
                    'city' => $_POST['city'],
                    'street' => $_POST['street'],
                    'details' => $_POST['details'],
                    'phone' => $_POST['phone']
                ],
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];

            $ordersCollection->insertOne($orderData);

            // خصم المخزون
            foreach ($group['items'] as $item) {
                $productsCollection->updateOne(
                    ['_id' => $item['product']['_id']],
                    ['$inc' => ['stock' => - $item['qty']]]
                );
            }
            
            $orders_created++;
        }

        if ($orders_created > 0) {
            unset($_SESSION['cart']);
            echo "<script>
                alert('🎉 تم تقديم الطلبات بنجاح! شكراً لتسوقك معنا.');
                window.location.href='profile.php';
            </script>";
            exit();
        }
    }

} catch (Exception $e) {
    $msg = "خطأ: " . $e->getMessage();
    $msg_type = "error";
    // في حال الخطأ، نعيد بناء $cart_grouped لأن الـ code flow قد يكون انقطع
    // لكن بما أننا بنيناه في البداية، سيظل متاحاً للعرض في الأسفل
}
?>


<div class="h-24"></div>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-black mb-8 text-center"><i class="fas fa-check-circle text-brand-gold"></i> إتمام الطلب</h1>

        <?php if(!empty($msg)): ?>
        <div class="mb-6 p-4 rounded-xl font-bold <?php echo $msg_type == 'success' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500'; ?>">
            <?php echo $msg; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <!-- نموذج العنوان -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 h-fit" data-aos="fade-left">
                <h3 class="text-xl font-bold mb-6 text-brand-gold border-b border-slate-700 pb-2">بيانات التوصيل</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-slate-400 mb-2">رقم الهاتف للتواصل</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 focus:border-brand-gold outline-none">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">المدينة</label>
                            <select name="city" required class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 focus:border-brand-gold outline-none">
                                <option value="Sana'a">صنعاء</option>
                                <option value="Aden">عدن</option>
                                <option value="Taiz">تعز</option>
                                <option value="Ibb">إب</option>
                                <option value="Hodeidah">الحديدة</option>
                                <option value="Hadramout">حضرموت</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">الشارع / الحي</label>
                            <input type="text" name="street" required placeholder="مثال: شارع الستين" class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 focus:border-brand-gold outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">تفاصيل إضافية (اختياري)</label>
                        <textarea name="details" rows="3" placeholder="أقرب معلم، رقم المنزل، ملاحظات للتوصيل..." class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 focus:border-brand-gold outline-none"></textarea>
                    </div>
                    
                    <div class="bg-blue-500/10 p-4 rounded-xl text-sm text-blue-300 flex items-start gap-3 mt-4">
                        <i class="fas fa-info-circle text-lg mt-1"></i>
                        <p>الدفع حالياً "عند الاستلام" فقط. سيتم التواصل معك لتأكيد الطلب وتحديد موعد التسليم.</p>
                    </div>
                </div>
            </div>

            <!-- تفاصيل الشحنات والدفع -->
            <div class="space-y-6" data-aos="fade-right">
                <h3 class="text-xl font-bold text-brand-gold border-b border-slate-700 pb-2">ملخص الشحنات والدفع</h3>
                
                <?php if (empty($cart_grouped)): ?>
                    <p class="text-red-400">السلة فارغة أو حدث خطأ في تحميل المنتجات.</p>
                <?php else: ?>
                    
                    <?php foreach ($cart_grouped as $vid => $group): ?>
                        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 relative overflow-hidden">
                            <!-- رأس البطاقة: اسم المتجر -->
                            <div class="flex items-center gap-3 mb-4 pb-4 border-b border-slate-700/50">
                                <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-brand-gold">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div>
                                    <div class="text-sm text-slate-400">بائع:</div>
                                    <div class="font-bold text-white"><?php echo htmlspecialchars($group['vendor_info']['store_name']); ?></div>
                                </div>
                            </div>

                            <!-- قائمة المنتجات -->
                            <div class="space-y-3 mb-6">
                                <?php foreach ($group['items'] as $item): ?>
                                    <div class="flex justify-between items-center bg-slate-900/50 p-3 rounded-xl text-sm">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded bg-slate-700 overflow-hidden">
                                                <img src="../<?php echo $item['product']['image'] ?? 'assets/images/no-image.png'; ?>" class="w-full h-full object-cover">
                                            </div>
                                            <span class="text-slate-200"><?php echo htmlspecialchars($item['product']['name']); ?> <span class="text-slate-500 text-xs">x<?php echo $item['qty']; ?></span></span>
                                        </div>
                                        <div class="font-bold text-brand-gold"><?php echo number_format($item['line_total']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- المجموع الفرعي -->
                            <div class="flex justify-between items-center mb-6 pt-2 border-t border-slate-700/50 border-dashed">
                                <span class="text-slate-400">المجموع الفرعي:</span>
                                <span class="text-xl font-bold text-white"><?php echo number_format($group['subtotal']); ?> <span class="text-sm text-brand-gold">ر.ي</span></span>
                            </div>

                            <!-- خيارات الدفع لهذا المتجر -->
                            <div class="bg-slate-900/80 rounded-xl p-4 border border-slate-700">
                                <h4 class="font-bold text-sm text-slate-300 mb-3"><i class="fas fa-credit-card"></i> طريقة الدفع:</h4>
                                
                                <div class="flex flex-col gap-3">
                                    <!-- خيار الدفع عند الاستلام -->
                                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-slate-700 bg-slate-800 hover:border-brand-gold transition-all">
                                        <input type="radio" name="payment_method_<?php echo $vid; ?>" value="cod" checked class="accent-brand-gold w-5 h-5" onclick="togglePaymentInfo('<?php echo $vid; ?>', 'cod')">
                                        <div>
                                            <div class="font-bold">الدفع عند الاستلام</div>
                                            <div class="text-xs text-slate-500">ادفع نقداً عند استلام الطلب</div>
                                        </div>
                                    </label>

                                    <!-- خيار التحويل -->
                                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-slate-700 bg-slate-800 hover:border-brand-gold transition-all">
                                        <input type="radio" name="payment_method_<?php echo $vid; ?>" value="transfer" class="accent-brand-gold w-5 h-5" onclick="togglePaymentInfo('<?php echo $vid; ?>', 'transfer')">
                                        <div>
                                            <div class="font-bold">تحويل بنكي / محفظة</div>
                                            <div class="text-xs text-slate-500">حول المبلغ وأرفق صورة السند</div>
                                        </div>
                                    </label>
                                </div>

                                <!-- تفاصيل التحويل (مخفي افتراضياً) -->
                                <div id="transfer_info_<?php echo $vid; ?>" class="hidden mt-4 pt-4 border-t border-slate-700/50 animate-fade-in">
                                    <?php 
                                        // جلب طرق الدفع من المحفظة الخاصة بالتاجر
                                        try {
                                            $v_methods = $db->vendor_payment_methods->find(
                                                ['vendor_id' => $group['vendor_info']['_id']]
                                            )->toArray();
                                        } catch(Exception $e) { $v_methods = []; }

                                        if (!empty($v_methods)): 
                                    ?>
                                        <div class="bg-blue-500/10 p-4 rounded-xl mb-4 text-sm border border-blue-500/20">
                                            <div class="font-bold text-blue-300 mb-3 flex items-center gap-2">
                                                <i class="fas fa-info-circle"></i> حسابات التحويل المتاحة:
                                            </div>
                                            <div class="space-y-3">
                                                <?php foreach($v_methods as $method): ?>
                                                <div class="bg-slate-900/50 p-3 rounded-lg border border-slate-700/50 flex justify-between items-center">
                                                    <div>
                                                        <div class="font-bold text-white"><?php echo htmlspecialchars($method['provider_name']); ?></div>
                                                        <div class="text-xs text-slate-400"><?php echo htmlspecialchars($method['account_name']); ?></div>
                                                    </div>
                                                    <div class="font-mono text-brand-gold font-bold text-lg select-all">
                                                        <?php echo htmlspecialchars($method['account_number']); ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="text-xs text-slate-400 mt-3 text-center">يرجى التحويل لإحدى الحسابات أعلاه وإرفاق صورة السند.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-yellow-500/10 p-3 rounded-lg mb-4 text-xs text-yellow-500 border border-yellow-500/20">
                                            <i class="fas fa-exclamation-triangle"></i> لم يقم التاجر بإضافة حسابات استلام في محفظته. يرجى الدفع عند الاستلام.
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <label class="block text-xs text-slate-400 mb-2">صورة سند التحويل</label>
                                        <input type="file" name="receipt_<?php echo $vid; ?>" accept="image/*,application/pdf" class="block w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer border border-slate-700 rounded-lg p-1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

                <div class="sticky bottom-4 bg-slate-900/90 backdrop-blur border border-slate-700 p-4 rounded-2xl shadow-2xl z-10 text-center">
                    <div class="text-xl font-bold mb-3">الإجمالي الكلي: <span class="text-brand-gold"><?php echo number_format($grand_total); ?> ر.ي</span></div>
                    <button type="submit" name="place_order" class="w-full bg-gradient-to-r from-brand-gold to-orange-500 text-brand-dark font-black py-4 rounded-xl shadow-lg shadow-orange-500/20 transition-all transform hover:-translate-y-1 flex justify-center items-center gap-2">
                        <i class="fas fa-check-double"></i> تأكيد جميع الطلبات (<?php echo count($cart_grouped); ?>)
                    </button>
                    <a href="cart.php" class="block mt-2 text-xs text-slate-400 hover:text-white">تعديل السلة</a>
                </div>

            </div>
            
            <script>
                function togglePaymentInfo(vid, method) {
                    const infoDiv = document.getElementById('transfer_info_' + vid);
                    if (method === 'transfer') {
                        infoDiv.classList.remove('hidden');
                    } else {
                        infoDiv.classList.add('hidden');
                    }
                }
            </script>


        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
