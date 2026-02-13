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

    // جلب معرف المتجر
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $vendor = $db->vendors->findOne(['user_id' => $user_id]);

    if (!$vendor) die("حساب التاجر غير موجود");
    
    $vendor_id = $vendor['_id'];
    $store_name = $vendor['store_name'];

    $msg = "";
    $msg_type = "";

    // 2. إضافة طريقة دفع جديدة
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
        $provider = htmlspecialchars($_POST['provider_name']);
        $acc_name = htmlspecialchars($_POST['account_name']);
        $acc_num = htmlspecialchars($_POST['account_number']);

        if (!empty($provider) && !empty($acc_num)) {
            $db->vendor_payment_methods->insertOne([
                'vendor_id' => $vendor_id,
                'provider_name' => $provider,
                'account_name' => $acc_name,
                'account_number' => $acc_num,
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            $msg = "تم إضافة الحساب البنكي/المحفظة بنجاح.";
            $msg_type = "success";
        }
    }

    // 3. حذف طريقة دفع
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
        $method_id = new MongoDB\BSON\ObjectId($_POST['method_id']);
        $db->vendor_payment_methods->deleteOne(['_id' => $method_id, 'vendor_id' => $vendor_id]);
        
        $msg = "تم حذف الحساب.";
        $msg_type = "error";
    }

    // 4. جلب طرق الدفع الحالية
    $methods = $db->vendor_payment_methods->find(
        ['vendor_id' => $vendor_id],
        ['sort' => ['_id' => -1]]
    )->toArray();

    // 5. حساب الرصيد القابل للسحب
    // (فقط الطلبات المسلمة والتي دفعها العميل عند الاستلام - لأن المبالغ المحولة تصلك مباشرة)
    $balancePipeline = [
        ['$match' => [
            'vendor_id' => $vendor_id, 
            'status' => 'delivered',
            'payment_method' => 'cod' // فقط الدفع عند الاستلام يضاف للرصيد القابل للسحب (لأن المنصة قد تكون هي من حصلته)
        ]],
        ['$group' => [
            '_id' => null,
            'total_sales' => ['$sum' => '$total_amount']
        ]]
    ];
    
    $balanceResult = $db->orders->aggregate($balancePipeline)->toArray();
    $current_balance = $balanceResult[0]['total_sales'] ?? 0;

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحفظة | <?php echo htmlspecialchars($store_name); ?></title>
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
        .input-dark {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .input-dark:focus { border-color: #fbbf24; outline: none; }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans overflow-x-hidden">

    <div class="flex min-h-screen">
        
        <!-- الشريط الجانبي -->
        <aside class="w-64 bg-brand-sidebar border-l border-slate-800 hidden md:flex flex-col fixed h-full z-20">
            <div class="h-24 flex flex-col items-center justify-center border-b border-slate-800 p-4">
                <div class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($store_name); ?></div>
                <div class="text-xs text-green-400 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> متصل الآن</div>
            </div>
            <nav class="flex-1 overflow-y-auto py-6">
                <ul class="space-y-2 px-4">
                    <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-home"></i> نظرة عامة</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box"></i> منتجاتي</a></li>
                    <li><a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-shopping-bag"></i> الطلبات</a></li>
                    <li><a href="wallet.php" class="flex items-center gap-3 px-4 py-3 bg-brand-accent text-white rounded-xl font-bold shadow-lg shadow-blue-500/20"><i class="fas fa-wallet"></i> المحفظة</a></li>
                    <li><a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-cog"></i> إعدادات المتجر</a></li>
                </ul>
            </nav>
             <div class="p-4 border-t border-slate-800">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/10 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-lg font-bold text-white"><?php echo htmlspecialchars($store_name); ?></div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-xl font-bold text-center <?php echo $msg_type == 'success' ? 'bg-green-600/20 text-green-500' : 'bg-red-600/20 text-red-500'; ?>">
                <?php echo $msg; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- العمود الأيمن: الرصيد وإضافة حساب -->
                <div class="space-y-8">
                    
                    <!-- بطاقة الرصيد -->
                    <div class="bg-gradient-to-br from-brand-gold to-yellow-600 rounded-2xl p-6 text-brand-dark shadow-xl relative overflow-hidden">
                        <div class="relative z-10">
                            <h3 class="font-bold text-lg mb-1 opacity-80">الرصيد القابل للسحب</h3>
                            <div class="text-4xl font-black mb-4"><?php echo number_format($current_balance); ?> <span class="text-lg">ر.ي</span></div>
                            <button class="bg-brand-dark/20 hover:bg-brand-dark/30 text-brand-dark px-4 py-2 rounded-lg font-bold text-sm transition-colors">
                                <i class="fas fa-history"></i> سجل العمليات
                            </button>
                        </div>
                        <i class="fas fa-wallet absolute -bottom-6 -left-6 text-9xl opacity-10"></i>
                    </div>

                    <!-- نموذج إضافة حساب -->
                    <div class="glass-card rounded-2xl p-6">
                        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                            <i class="fas fa-plus-circle text-brand-accent"></i> إضافة وسيلة استلام
                        </h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add">
                            
                            <div>
                                <label class="text-xs text-slate-400 block mb-1">اسم المحفظة / البنك</label>
                                <select name="provider_name" class="w-full p-3 rounded-xl input-dark text-sm">
                                    <option value="الكريمي">بنك الكريمي (Kuraimi)</option>
                                    <option value="ون كاش">ون كاش (OneCash)</option>
                                    <option value="موبايل موني">موبايل موني (Mobile Money)</option>
                                    <option value="جوالي">محفظة جوالي</option>
                                    <option value="بنك التضامن">بنك التضامن</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs text-slate-400 block mb-1">اسم صاحب الحساب</label>
                                <input type="text" name="account_name" required class="w-full p-3 rounded-xl input-dark text-sm" placeholder="مثال: محمد احمد علي">
                            </div>

                            <div>
                                <label class="text-xs text-slate-400 block mb-1">رقم الحساب / الهاتف</label>
                                <input type="text" name="account_number" required class="w-full p-3 rounded-xl input-dark text-sm" placeholder="مثال: 77xxxxxxx">
                            </div>

                            <button type="submit" class="w-full bg-brand-accent hover:bg-blue-600 text-white py-3 rounded-xl font-bold transition-all shadow-lg shadow-blue-500/20">
                                حفظ الحساب
                            </button>
                        </form>
                    </div>
                </div>

                <!-- العمود الأيسر: قائمة الحسابات المضافة -->
                <div class="lg:col-span-2">
                    <h2 class="text-2xl font-bold mb-6">حسابات الاستلام الخاصة بي</h2>
                    <p class="text-slate-400 mb-6 text-sm">هذه الحسابات ستظهر للزبون عند اختيار الدفع عبر التحويل لمتجرك.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if(count($methods) > 0): ?>
                            <?php foreach($methods as $method): ?>
                                <!-- كرت المحفظة -->
                                <div class="glass-card p-5 rounded-2xl relative group hover:border-brand-gold/50 transition-colors">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-full bg-slate-700 flex items-center justify-center text-xl">
                                                <?php if($method['provider_name'] == 'الكريمي'): ?>
                                                    <i class="fas fa-university text-green-400"></i>
                                                <?php elseif($method['provider_name'] == 'ون كاش' || $method['provider_name'] == 'جوالي'): ?>
                                                    <i class="fas fa-mobile-alt text-yellow-400"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-money-check-alt text-blue-400"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-lg"><?php echo htmlspecialchars($method['provider_name']); ?></h4>
                                                <span class="text-xs text-green-400 bg-green-500/10 px-2 py-0.5 rounded-full">نشط</span>
                                            </div>
                                        </div>
                                        
                                        <!-- زر الحذف -->
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="method_id" value="<?php echo $method['_id']; ?>">
                                            <button type="submit" class="text-slate-500 hover:text-red-500 transition-colors">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between border-b border-slate-700/50 pb-2">
                                            <span class="text-slate-400">الاسم:</span>
                                            <span class="font-bold"><?php echo htmlspecialchars($method['account_name']); ?></span>
                                        </div>
                                        <div class="flex justify-between pt-1">
                                            <span class="text-slate-400">الرقم:</span>
                                            <span class="font-mono text-brand-gold font-bold text-lg"><?php echo htmlspecialchars($method['account_number']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-2 text-center py-12 glass-card rounded-2xl border-dashed border-2 border-slate-600">
                                <i class="fas fa-wallet text-5xl text-slate-600 mb-4"></i>
                                <h3 class="text-xl font-bold text-slate-400">لا توجد حسابات مضافة</h3>
                                <p class="text-slate-500">أضف حساباتك ليتمكن الزبائن من تحويل المبالغ لك مباشرة.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>
</body>
</html>