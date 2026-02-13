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

$msg = "";
$msg_type = "";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // 2. تحديث البيانات
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // تحديث المعلومات الأساسية
        if (isset($_POST['update_info'])) {
            $fname = $_POST['first_name'];
            $lname = $_POST['last_name'];
            // لا نسمح بتغيير رقم الهاتف بسهولة لأنه هوية الدخول (يحتاج تحقق OTP في الواقع)
            
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
            if ($stmt->execute([$fname, $lname, $user_id])) {
                $_SESSION['user_name'] = $fname . ' ' . $lname; // تحديث الاسم في الجلسة
                $msg = "تم تحديث بياناتك بنجاح.";
                $msg_type = "success";
            }
        }

        // تغيير كلمة المرور
        if (isset($_POST['change_password'])) {
            $current_pass = $_POST['current_password'];
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            // جلب كلمة السر الحالية للتحقق
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (password_verify($current_pass, $user['password'])) {
                if ($new_pass === $confirm_pass) {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $user_id]);
                    $msg = "تم تغيير كلمة المرور بنجاح.";
                    $msg_type = "success";
                } else {
                    $msg = "كلمة المرور الجديدة غير متطابقة.";
                    $msg_type = "error";
                }
            } else {
                $msg = "كلمة المرور الحالية غير صحيحة.";
                $msg_type = "error";
            }
        }
    }

    // 3. جلب بيانات المستخدم الحالية
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي | الصقر مول</title>
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

    <!-- ناف بار بسيط -->
    <nav class="bg-slate-900 border-b border-white/5 py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="../index.php" class="text-xl font-bold flex items-center gap-2 hover:text-brand-gold transition">
                <i class="fas fa-home"></i> الرئيسية
            </a>
            <div class="font-bold text-lg text-brand-gold">إعدادات الحساب</div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-4xl">

        <?php if(!empty($msg)): ?>
        <div class="mb-6 p-4 rounded-xl font-bold text-center <?php echo $msg_type == 'success' ? 'bg-green-600/20 text-green-500' : 'bg-red-600/20 text-red-500'; ?>">
            <?php echo $msg; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- بطاقة المعلومات -->
            <div class="glass-panel p-6 rounded-2xl text-center h-fit">
                <div class="w-24 h-24 bg-brand-gold/20 rounded-full flex items-center justify-center mx-auto mb-4 text-brand-gold text-4xl border border-brand-gold/30">
                    <i class="fas fa-user"></i>
                </div>
                <h2 class="text-xl font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p class="text-slate-400 text-sm mb-4"><?php echo htmlspecialchars($user['phone']); ?></p>
                <div class="bg-slate-800 rounded-xl p-3 text-xs text-slate-300">
                    تاريخ الانضمام: <br>
                    <span class="font-mono text-brand-gold"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
                </div>
            </div>

            <!-- نماذج التعديل -->
            <div class="md:col-span-2 space-y-8">
                
                <!-- البيانات الشخصية -->
                <div class="glass-panel p-6 rounded-2xl">
                    <h3 class="font-bold text-lg mb-6 border-b border-slate-700 pb-2">تحديث المعلومات</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="update_info" value="1">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">الاسم الأول</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required class="w-full rounded-xl p-3 input-field">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">الاسم الأخير</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required class="w-full rounded-xl p-3 input-field">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-1">رقم الهاتف (للقراءة فقط)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled class="w-full rounded-xl p-3 bg-slate-800 text-slate-500 cursor-not-allowed border border-slate-700">
                        </div>
                        <button type="submit" class="bg-brand-gold hover:bg-yellow-500 text-brand-dark font-bold py-2 px-6 rounded-xl transition-all">حفظ التعديلات</button>
                    </form>
                </div>

                <!-- الأمان -->
                <div class="glass-panel p-6 rounded-2xl">
                    <h3 class="font-bold text-lg mb-6 border-b border-slate-700 pb-2 text-red-400">تغيير كلمة المرور</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="change_password" value="1">
                        <div>
                            <label class="block text-sm text-slate-400 mb-1">كلمة المرور الحالية</label>
                            <input type="password" name="current_password" required class="w-full rounded-xl p-3 input-field">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">كلمة المرور الجديدة</label>
                                <input type="password" name="new_password" required class="w-full rounded-xl p-3 input-field">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-400 mb-1">تأكيد الجديدة</label>
                                <input type="password" name="confirm_password" required class="w-full rounded-xl p-3 input-field">
                            </div>
                        </div>
                        <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white font-bold py-2 px-6 rounded-xl transition-all">تحديث كلمة المرور</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

</body>
</html>