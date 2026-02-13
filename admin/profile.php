<?php
session_start();
require_once '../includes/db.php';

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $usersCollection = $db->users;

    // 2. تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // أ. تحديث المعلومات الشخصية
        if (isset($_POST['update_info'])) {
            $first_name = htmlspecialchars($_POST['first_name']);
            $last_name = htmlspecialchars($_POST['last_name']);
            $email = htmlspecialchars($_POST['email']);
            
            // تحقق من عدم تكرار الإيميل (إذا تم تغييره)
            $existingUser = $usersCollection->findOne(['email' => $email, '_id' => ['$ne' => $user_id]]);
            if ($existingUser) {
                $msg = "عذراً، البريد الإلكتروني مستخدم بالفعل.";
                $msg_type = "error";
            } else {
                $usersCollection->updateOne(
                    ['_id' => $user_id],
                    ['$set' => [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                // تحديث الجلسة إذا تغير الاسم
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                
                $msg = "تم تحديث البيانات الشخصية بنجاح.";
                $msg_type = "success";
            }
        }

        // ب. تغيير كلمة المرور
        if (isset($_POST['change_pass'])) {
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            if (!empty($new_pass) && $new_pass === $confirm_pass) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                
                $usersCollection->updateOne(
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
    }

    // 3. جلب بيانات المسؤول
    $admin = $usersCollection->findOne(['_id' => $user_id]);
    
    if (!$admin) die("خطأ: حساب المسؤول غير موجود");

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي | لوحة الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-sidebar': '#1e293b',
                        'brand-gold': '#fbbf24',
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
        
        <!-- Sidebar -->
        <aside class="w-64 bg-brand-sidebar border-l border-slate-700 hidden md:flex flex-col fixed h-full z-20">
            <div class="h-20 flex items-center justify-center border-b border-slate-700">
                <div class="text-2xl font-black tracking-tighter flex items-center gap-2">
                    <i class="fas fa-eagle text-brand-gold"></i>
                    <span class="text-white">الصقر <span class="text-brand-gold">ADMIN</span></span>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-6">
                <ul class="space-y-2 px-4">
                    <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-th-large"></i> الرئيسية</a></li>
                    <li><a href="vendors.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-store"></i> إدارة المتاجر</a></li>
                    <li><a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-tags"></i> الأقسام</a></li>
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box-open"></i> المنتجات</a></li>
                    <li><a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-chart-line"></i> التقارير</a></li>
                    <li><a href="profile.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg"><i class="fas fa-user-cog"></i> الملف الشخصي</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/20 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> لوحة الإدارة</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-xl font-bold text-center <?php echo $msg_type == 'success' ? 'bg-green-600/20 text-green-500' : 'bg-red-600/20 text-red-500'; ?>">
                <?php echo $msg; ?>
            </div>
            <?php endif; ?>

            <div class="mb-8 border-b border-slate-800 pb-4">
                <h1 class="text-3xl font-bold mb-2">الملف الشخصي</h1>
                <p class="text-slate-400">تحديث بيانات حساب المسؤول وكلمة المرور.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- البيانات الشخصية -->
                <div class="glass-card rounded-2xl p-8">
                    <h3 class="font-bold text-xl mb-6 flex items-center gap-2 text-brand-gold">
                        <i class="fas fa-user-shield"></i> المعلومات الشخصية
                    </h3>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_info" value="1">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-400 mb-2">الاسم الأول</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" class="w-full p-3 rounded-xl input-dark">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-2">الاسم الأخير</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" class="w-full p-3 rounded-xl input-dark">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" class="w-full p-3 rounded-xl input-dark">
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">نوع الحساب</label>
                            <input type="text" value="مسؤول النظام (Admin)" disabled class="w-full p-3 rounded-xl bg-slate-800/50 text-brand-gold font-bold cursor-not-allowed border border-brand-gold/20">
                        </div>

                        <button type="submit" class="w-full bg-brand-gold hover:bg-yellow-500 text-brand-dark py-3 rounded-xl font-bold transition-all shadow-lg hover:shadow-brand-gold/20">حفظ التغييرات</button>
                    </form>
                </div>

                <!-- الأمان -->
                <div class="glass-card rounded-2xl p-8 h-fit">
                    <h3 class="font-bold text-xl mb-6 flex items-center gap-2 text-red-400">
                        <i class="fas fa-lock"></i> تغيير كلمة المرور
                    </h3>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="change_pass" value="1">
                        
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" required class="w-full p-3 rounded-xl input-dark" placeholder="••••••••">
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" required class="w-full p-3 rounded-xl input-dark" placeholder="••••••••">
                        </div>

                        <ul class="text-xs text-slate-500 space-y-1 list-disc list-inside bg-slate-900/50 p-3 rounded-lg">
                            <li>يجب أن تكون كلمة المرور قوية (أحرف وأرقام).</li>
                            <li>لا تشارك كلمة المرور مع أي شخص.</li>
                        </ul>

                        <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 text-white py-3 rounded-xl font-bold transition-all border border-slate-600 hover:border-slate-500">تحديث كلمة المرور</button>
                    </form>
                </div>

            </div>

        </main>
    </div>
</body>
</html>
