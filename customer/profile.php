<?php
// إعداد عنوان الصفحة
$page_title = 'الملف الشخصي | الصقر مول';
require_once 'includes/header.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='../login.php';</script>";
    exit();
}

$user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $usersCollection = $db->users;
    $ordersCollection = $db->orders;

    // معالجة تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $email = $_POST['email'] ?? ''; // Handle email input
            $new_phone = $_POST['phone'];
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            $updateData = [
                'first_name' => $first_name,
                'last_name' => $last_name,
            ];
            
            // تحديث الإيميل إذا تم إدخاله
            if (!empty($email)) {
                $updateData['email'] = $email;
            }

            // تحديث كلمة المرور إذا تم إدخالها
            if (!empty($password)) {
                if ($password === $confirm_password) {
                    $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
                } else {
                    $msg = "كلمات المرور غير متطابقة!";
                    $msg_type = "error";
                    // سنوقف التنفيذ هنا لتجنب تحديث البيانات الأخرى في حال وجود خطأ في كلمة المرور (اختياري، لكن أفضل)
                    // لكن بما أننا نريد تحديث الاسم حتى لو فشلت كلمة المرور، سأجعل الخطأ فقط لكلمة المرور
                    // أو الأفضل: إيقاف العملية بالكامل
                    $update_failed = true;
                }
            }
            
            
            if (!isset($update_failed)) {
                // التعامل مع تغيير رقم الهاتف (طلب معلق)
                $currentUser = $usersCollection->findOne(['_id' => $user_id]);
                if ($new_phone !== $currentUser['phone']) {
                    // بدلاً من التحديث المباشر، نضيف حقل طلب معلق
                    $usersCollection->updateOne(
                        ['_id' => $user_id],
                        ['$set' => [
                            'pending_update' => [
                                'type' => 'phone',
                                'value' => $new_phone,
                                'requested_at' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ]]
                    );
                    $msg = "تم تحديث البيانات. تغيير رقم الهاتف يتطلب موافقة الإدارة وسيتم تفعيله قريباً.";
                    $msg_type = "warning";
                } else {
                    $msg = "تم تحديث بياناتك بنجاح.";
                    $msg_type = "success";
                }

                // تنفيذ التحديث الأساسي (الاسم وكلمة المرور)
                $usersCollection->updateOne(
                    ['_id' => $user_id],
                    ['$set' => $updateData]
                );
                
                // تحديث الجلسة للاسم
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            }
        }
    }

    // جلب بيانات المستخدم
    $user = $usersCollection->findOne(['_id' => $user_id]);

    // جلب إحصائيات الطلبات
    $pipeline = [
        ['$match' => ['customer_id' => $user_id]], // استخدام $user_id (ObjectId) المعرف في السطر 12
        ['$group' => [
            '_id' => null,
            'total_spent' => ['$sum' => '$total_amount'],
            'count' => ['$sum' => 1]
        ]]
    ];
    
    // Listing orders
    $orders = $ordersCollection->find(
        ['customer_id' => $user_id], 
        ['sort' => ['created_at' => -1]]
    )->toArray();

    $stats = $ordersCollection->aggregate([
        ['$match' => [
            'customer_id' => $user_id,
            'status' => ['$ne' => 'cancelled'] // استبعاد الطلبات الملغية من الحساب
        ]],
        ['$group' => [
            '_id' => null,
            'total_orders' => ['$sum' => 1],
            'total_spent' => ['$sum' => '$total_amount'] // تأكد أن الحقل total_amount موجود في الاوردر
        ]]
    ])->toArray();

    $total_orders = $stats[0]['total_orders'] ?? 0;
    $total_spent = $stats[0]['total_spent'] ?? 0;

} catch (Exception $e) {
    $msg = "حدث خطأ: " . $e->getMessage();
    $msg_type = "error";
}
?>

<div class="h-24"></div> <!-- Spacer for fixed header -->

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row gap-8">
        
        <!-- القائمة الجانبية -->
        <aside class="w-full md:w-1/4" data-aos="fade-left">
            <div class="bg-slate-800 rounded-2xl p-6 border border-slate-700 sticky top-28">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-brand-gold rounded-full mx-auto flex items-center justify-center text-4xl text-brand-dark font-black mb-3">
                        <?php echo mb_substr($user['first_name'], 0, 1); ?>
                    </div>
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($user['email'] ?? 'لا يوجد بريد إلكتروني'); ?></p>
                </div>

                <nav class="space-y-2">
                    <button onclick="switchTab('overview')" id="btn-overview" class="w-full text-right px-4 py-3 rounded-xl transition-all flex items-center gap-3 bg-brand-gold text-brand-dark font-bold tab-btn">
                        <i class="fas fa-home"></i> نظرة عامة
                    </button>
                    <button onclick="switchTab('orders')" id="btn-orders" class="w-full text-right px-4 py-3 rounded-xl transition-all flex items-center gap-3 text-slate-400 hover:bg-slate-700 hover:text-white tab-btn">
                        <i class="fas fa-box-open"></i> طلباتي
                    </button>
                    <button onclick="switchTab('settings')" id="btn-settings" class="w-full text-right px-4 py-3 rounded-xl transition-all flex items-center gap-3 text-slate-400 hover:bg-slate-700 hover:text-white tab-btn">
                        <i class="fas fa-cog"></i> الإعدادات
                    </button>
                    <a href="../logout.php" class="block w-full text-right px-4 py-3 rounded-xl transition-all flex items-center gap-3 text-red-400 hover:bg-red-500/10">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
                </nav>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="w-full md:w-3/4" data-aos="fade-right">
            
            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-xl font-bold <?php echo $msg_type == 'success' ? 'bg-green-500/20 text-green-500' : ($msg_type == 'warning' ? 'bg-yellow-500/20 text-yellow-500' : 'bg-red-500/20 text-red-500'); ?>">
                <?php echo $msg; ?>
            </div>
            <?php endif; ?>

            <!-- تبويب: نظرة عامة -->
            <div id="tab-overview" class="tab-content transition-opacity duration-300">
                <h2 class="text-2xl font-bold mb-6">مرحباً بك، <?php echo htmlspecialchars($user['first_name']); ?> 👋</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-gradient-to-br from-brand-gold to-yellow-600 rounded-2xl p-6 relative overflow-hidden text-brand-dark">
                        <div class="z-10 relative">
                            <div class="text-lg font-bold mb-1 opacity-80">إجمالي المصروفات</div>
                            <div class="text-4xl font-black"><?php echo number_format($total_spent); ?> <span class="text-lg">ر.ي</span></div>
                        </div>
                        <i class="fas fa-wallet absolute -bottom-4 -left-4 text-8xl opacity-20"></i>
                    </div>

                    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 relative overflow-hidden">
                        <div class="z-10 relative">
                            <div class="text-lg font-bold mb-1 text-slate-400">عدد الطلبات</div>
                            <div class="text-4xl font-black"><?php echo $total_orders; ?></div>
                        </div>
                        <i class="fas fa-shopping-bag absolute -bottom-4 -left-4 text-8xl text-slate-700 opacity-50"></i>
                    </div>
                </div>

                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-4 text-brand-gold">آخر طلباتك</h3>
                    <?php if(count($orders) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach(array_slice($orders, 0, 3) as $order): ?>
                            <div class="flex items-center justify-between p-4 bg-slate-800 rounded-xl border border-slate-700/50">
                                <div>
                                    <div class="font-bold text-white">طلب #<?php echo substr($order['_id'], -6); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo $order['created_at']->toDateTime()->format('Y-m-d h:i A'); ?></div>
                                </div>
                                <div class="text-left">
                                    <div class="font-bold text-brand-gold"><?php echo number_format($order['total_amount']); ?> ر.ي</div>
                                    <span class="text-xs px-2 py-1 rounded-full bg-slate-700 text-slate-300">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <button onclick="switchTab('orders')" class="w-full text-center text-sm text-slate-400 hover:text-white mt-2">عرض كل الطلبات</button>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-slate-500">لست لديك أي طلبات بعد.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- تبويب: طلباتي -->
            <div id="tab-orders" class="hidden tab-content transition-opacity duration-300">
                <h2 class="text-2xl font-bold mb-6">سجل الطلبات</h2>
                <?php if(count($orders) > 0): ?>
                    <div class="grid gap-4">
                        <?php foreach($orders as $order): ?>
                        <div class="bg-slate-800 border border-slate-700 rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4 hover:border-brand-gold/50 transition-colors">
                            <div class="flex items-center gap-4 w-full md:w-auto">
                                <div class="w-12 h-12 bg-slate-700 rounded-full flex items-center justify-center text-brand-gold">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-lg">طلب #<?php echo substr($order['_id'], -8); ?></div>
                                    <div class="text-sm text-slate-400">
                                        <i class="far fa-clock"></i> <?php echo $order['created_at']->toDateTime()->format('Y-m-d h:i A'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-8 w-full md:w-auto justify-between md:justify-end">
                                <div class="text-center">
                                    <div class="text-xs text-slate-400">الإجمالي</div>
                                    <div class="font-bold text-brand-gold text-lg"><?php echo number_format($order['total_amount']); ?> ر.ي</div>
                                </div>
                                
                                <div>
                                    <?php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-500/20 text-yellow-500',
                                            'processing' => 'bg-blue-500/20 text-blue-500',
                                            'shipped' => 'bg-purple-500/20 text-purple-500',
                                            'delivered' => 'bg-green-500/20 text-green-500',
                                            'cancelled' => 'bg-red-500/20 text-red-500',
                                        ];
                                        $statusClass = $statusColors[$order['status']] ?? 'bg-slate-700 text-slate-300';
                                        
                                        $statusNames = [
                                            'pending' => 'قيد الانتظار',
                                            'processing' => 'جاري التجهيز',
                                            'shipped' => 'تم الشحن',
                                            'delivered' => 'تم التسليم',
                                            'cancelled' => 'ملغي',
                                        ];
                                        $statusText = $statusNames[$order['status']] ?? $order['status'];
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                                
                                <!-- زر تفاصيل -->
                                <a href="order_details.php?id=<?php echo $order['_id']; ?>" class="w-8 h-8 rounded-full bg-slate-700 hover:bg-white hover:text-brand-dark flex items-center justify-center transition-all" title="تتبع الطلب">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-20 bg-slate-800/50 rounded-2xl border border-slate-700">
                        <i class="fas fa-shopping-basket text-6xl text-slate-600 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">سجل طلباتك فارغ</h3>
                        <p class="text-slate-400 mb-6">لم تقم بإجراء أي عملية شراء حتى الآن.</p>
                        <a href="index.php" class="inline-block bg-brand-gold text-brand-dark font-bold py-2 px-6 rounded-full hover:bg-white transition-colors">تصفح المنتجات</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- تبويب: الإعدادات -->
            <div id="tab-settings" class="hidden tab-content transition-opacity duration-300">
                <h2 class="text-2xl font-bold mb-6">إعدادات الحساب</h2>
                
                <div class="grid grid-cols-1 gap-8">
                    
                    <!-- المعلومات الشخصية -->
                    <div class="bg-slate-800/50 backdrop-blur-md border border-slate-700 rounded-2xl p-8 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-brand-gold to-brand-accent"></div>
                        <h3 class="font-bold text-xl mb-6 flex items-center gap-2 text-brand-gold">
                            <i class="fas fa-user-edit"></i> المعلومات الشخصية
                        </h3>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm text-slate-400 mb-2 font-bold">الاسم الأول</label>
                                    <div class="relative">
                                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 pl-10 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold outline-none transition-all">
                                        <i class="fas fa-user absolute left-4 top-3.5 text-slate-500"></i>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-400 mb-2 font-bold">الاسم الأخير</label>
                                    <div class="relative">
                                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 pl-10 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold outline-none transition-all">
                                        <i class="fas fa-user absolute left-4 top-3.5 text-slate-500"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm text-slate-400 mb-2 font-bold">البريد الإلكتروني</label>
                                <div class="relative">
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 pl-10 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold outline-none transition-all" placeholder="example@domain.com">
                                    <i class="fas fa-envelope absolute left-4 top-3.5 text-slate-500"></i>
                                </div>
                                <p class="text-xs text-slate-500 mt-1 mr-1">اختياري: يمكنك إضافة بريدك الإلكتروني لاستلام الإشعارات.</p>
                            </div>

                            <div>
                                <label class="block text-sm text-slate-400 mb-2 font-bold">رقم الهاتف</label>
                                <div class="relative">
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 pl-10 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold outline-none transition-all">
                                    <i class="fas fa-phone absolute left-4 top-3.5 text-slate-500"></i>
                                </div>
                                <?php if(isset($user['pending_update']) && $user['pending_update']['type'] === 'phone'): ?>
                                    <div class="mt-3 text-sm text-yellow-500 bg-yellow-500/10 border border-yellow-500/20 p-3 rounded-xl flex items-center gap-3 animate-pulse">
                                        <i class="fas fa-clock text-xl"></i>
                                        <div>
                                            <div class="font-bold">طلب قيد المراجعة</div>
                                            <div class="text-xs opacity-80">طلبت تغيير الرقم إلى: <b><?php echo htmlspecialchars($user['pending_update']['value']); ?></b></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- قسم الأمان -->
                            <div class="pt-6 mt-6 border-t border-slate-700/50">
                                <h3 class="font-bold text-lg mb-4 text-red-400 flex items-center gap-2">
                                    <i class="fas fa-shield-alt"></i> الأمان وكلمة المرور
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm text-slate-400 mb-2 font-bold">كلمة المرور الجديدة</label>
                                        <div class="relative">
                                            <input type="password" name="password" class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 pl-10 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all" placeholder="••••••••">
                                            <i class="fas fa-lock absolute left-4 top-3.5 text-slate-500"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm text-slate-400 mb-2 font-bold">تأكيد كلمة المرور</label>
                                        <div class="relative">
                                            <input type="password" name="confirm_password" class="w-full bg-slate-900 border border-slate-600 rounded-xl px-4 py-3 pl-10 focus:border-red-500 focus:ring-1 focus:ring-red-500 outline-none transition-all" placeholder="••••••••">
                                            <i class="fas fa-check-circle absolute left-4 top-3.5 text-slate-500"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mt-2 mr-1">اترك الحقول فارغة إذا كنت لا ترغب بتغيير كلمة المرور.</p>
                            </div>

                            <div class="pt-4 flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-brand-gold to-yellow-600 hover:from-yellow-400 hover:to-yellow-500 text-brand-dark font-bold py-4 px-10 rounded-xl shadow-lg shadow-brand-gold/20 transition-all transform hover:-translate-y-1 flex items-center gap-2">
                                    <i class="fas fa-save"></i> حفظ التغييرات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Hide all contents
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
    });
    
    // Show selected content
    document.getElementById('tab-' + tabId).classList.remove('hidden');
    
    // Reset buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-brand-gold', 'text-brand-dark', 'font-bold');
        btn.classList.add('text-slate-400');
    });
    
    // Highlight selected button
    const activeBtn = document.getElementById('btn-' + tabId);
    activeBtn.classList.remove('text-slate-400');
    activeBtn.classList.add('bg-brand-gold', 'text-brand-dark', 'font-bold');
}
</script>

<?php require_once 'includes/footer.php'; ?>
