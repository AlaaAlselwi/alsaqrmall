<?php
session_start();
require_once '../includes/db.php';

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);

    // 2. تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // أ. تحديث الشعار والوصف
        if (isset($_POST['update_info'])) {
            $store_name = htmlspecialchars($_POST['store_name']);
            $description = htmlspecialchars($_POST['description']);
            
            $updateData = [
                'store_name' => $store_name,
                'description' => $description
            ];

            // رفع الشعار
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $new_name = "vendor_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
                $target = "../uploads/vendors/" . $new_name;
                
                if (!is_dir("../uploads/vendors")) mkdir("../uploads/vendors", 0777, true);
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                    $updateData['logo'] = "uploads/vendors/" . $new_name;
                }
            }

            $db->vendors->updateOne(
                ['user_id' => $user_id],
                ['$set' => $updateData]
            );

            $msg = "تم تحديث معلومات المتجر بنجاح.";
            $msg_type = "success";
        }

        // ب. تغيير كلمة المرور
        if (isset($_POST['change_pass'])) {
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            if (!empty($new_pass) && $new_pass === $confirm_pass) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                
                $db->users->updateOne(
                    ['_id' => $user_id],
                    ['$set' => ['password' => $hashed]]
                );
                
                $msg = "تم تغيير كلمة المرور بنجاح.";
                $msg_type = "success";
            } else {
                $msg = "كلمات المرور غير متطابقة.";
                $msg_type = "error";
            }
        }
        // ج. تحديث بيانات الدفع
        if (isset($_POST['update_payment'])) {
            $payment_info = [
                'bank_name' => htmlspecialchars($_POST['bank_name']),
                'account_number' => htmlspecialchars($_POST['account_number']),
                'account_name' => htmlspecialchars($_POST['account_name'])
            ];

            $db->vendors->updateOne(
                ['user_id' => $user_id],
                ['$set' => ['payment_info' => $payment_info]]
            );

            $msg = "تم حفظ بيانات الدفع بنجاح.";
            $msg_type = "success";
        }
    }

    // 3. جلب البيانات الحالية (دمج بيانات التاجر والمستخدم)
    $pipeline = [
        ['$match' => ['user_id' => $user_id]],
        ['$lookup' => [
            'from' => 'users',
            'localField' => 'user_id',
            'foreignField' => '_id',
            'as' => 'user_data'
        ]],
        ['$unwind' => '$user_data']
    ];
    
    $result = $db->vendors->aggregate($pipeline)->toArray();
    
    if (empty($result)) die("خطأ: حساب التاجر غير موجود");
    
    $vendor = $result[0];
    $store_name = $vendor['store_name'];

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات | <?php echo htmlspecialchars($store_name); ?></title>
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
                    <li><a href="wallet.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-wallet"></i> المحفظة</a></li>
                    <li><a href="settings.php" class="flex items-center gap-3 px-4 py-3 bg-brand-accent text-white rounded-xl font-bold shadow-lg shadow-blue-500/20"><i class="fas fa-cog"></i> إعدادات المتجر</a></li>
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

            <h1 class="text-3xl font-bold mb-8">إعدادات المتجر</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- إعدادات عامة -->
                <div class="glass-card rounded-2xl p-8">
                    <h3 class="font-bold text-xl mb-6 flex items-center gap-2 text-brand-gold">
                        <i class="fas fa-store"></i> المعلومات العامة
                    </h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="update_info" value="1">
                        
                        <!-- الشعار -->
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-full bg-slate-700 overflow-hidden border-2 border-slate-600 relative group">
                                <?php if(isset($vendor['logo']) && !empty($vendor['logo'])): ?>
                                    <img src="../<?php echo $vendor['logo']; ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="flex items-center justify-center h-full text-slate-500"><i class="fas fa-camera"></i></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">تحديث الشعار</label>
                                <input type="file" name="logo" class="text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-slate-700 file:text-white hover:file:bg-slate-600 cursor-pointer">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">اسم المتجر</label>
                            <input type="text" name="store_name" value="<?php echo htmlspecialchars($vendor['store_name']); ?>" class="w-full p-3 rounded-xl input-dark">
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">نبذة عن المتجر</label>
                            <textarea name="description" rows="3" class="w-full p-3 rounded-xl input-dark"><?php echo htmlspecialchars($vendor['description']); ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">رقم الهاتف (للتواصل)</label>
                            <input type="text" value="<?php echo htmlspecialchars($vendor['user_data']['phone']); ?>" disabled class="w-full p-3 rounded-xl bg-slate-800/50 text-slate-500 cursor-not-allowed">
                        </div>

                        <button type="submit" class="w-full bg-brand-gold hover:bg-yellow-500 text-brand-dark py-3 rounded-xl font-bold transition-all">حفظ التغييرات</button>
                    </form>
                </div>


                <!-- الأمان -->
                <div class="glass-card rounded-2xl p-8 h-fit">
                    <h3 class="font-bold text-xl mb-6 flex items-center gap-2 text-red-400">
                        <i class="fas fa-shield-alt"></i> الأمان وكلمة المرور
                    </h3>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="change_pass" value="1">
                        
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" required class="w-full p-3 rounded-xl input-dark">
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" required class="w-full p-3 rounded-xl input-dark">
                        </div>

                        <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 text-white py-3 rounded-xl font-bold transition-all">تغيير كلمة المرور</button>
                    </form>
                </div>

            </div>

        </main>
    </div>
</body>
</html>