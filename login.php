<?php
session_start();
require_once 'includes/db.php'; // استدعاء مكتبة MongoDB

$message = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db = Database::connect();
        $usersCollection = $db->users;
        $vendorsCollection = $db->vendors;

        $phone = htmlspecialchars($_POST['phone']);
        $password_input = $_POST['password'];

        // 1. البحث عن المستخدم برقم الهاتف
        $user = $usersCollection->findOne(['phone' => $phone]);

        if ($user && password_verify($password_input, $user['password'])) {
            
            // 2. التحقق من نوع المستخدم
            if ($user['role'] === 'vendor') {
                // إذا كان تاجر، يجب التحقق من حالة المتجر
                // البحث في مجموعة vendors باستخدام user_id (ObjectId)
                $vendor = $vendorsCollection->findOne(['user_id' => $user['_id']]);

                if ($vendor) {
                    if ($vendor['status'] === 'pending') {
                        throw new Exception("حساب التاجر الخاص بك قيد المراجعة من قبل الإدارة. سيصلك إشعار عند التفعيل.");
                    } elseif ($vendor['status'] === 'suspended') {
                        throw new Exception("تم إيقاف حساب المتجر الخاص بك. يرجى مراجعة الإدارة.");
                    }
                    // تخزين اسم المتجر في الجلسة
                    $_SESSION['store_name'] = $vendor['store_name'];
                } else {
                    // نظرياً لا يجب أن يحدث هذا إذا كان التسجيل صحيحاً
                    throw new Exception("بيانات المتجر غير موجودة.");
                }
            }

            // 3. تسجيل الدخول بنجاح
            $_SESSION['user_id'] = (string)$user['_id']; // تحويل ObjectId إلى نص
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'];

            // التوجيه حسب الصلاحية
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } elseif ($user['role'] === 'vendor') {
                header("Location: vendor/dashboard.php");
            } else {
                header("Location: customer/index.php");
            }
            exit();

        } else {
            throw new Exception("رقم الهاتف أو كلمة المرور غير صحيحة.");
        }

    } catch(Exception $e) {
        $message = $e->getMessage();
        $msg_type = "error";
        // لو كانت الحالة انتظار، يمكننا جعل اللون أصفر
        if (strpos($e->getMessage(), 'قيد المراجعة') !== false) {
            $msg_type = "warning";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | الصقر مول</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
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
        .glass-panel {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-field {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: #fbbf24;
            box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.1);
            outline: none;
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- تنبيهات الخطأ/الانتظار -->
    <?php if(!empty($message)): ?>
    <div class="fixed top-10 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md p-4 rounded-xl shadow-2xl 
        <?php echo $msg_type == 'error' ? 'bg-red-600' : ($msg_type == 'warning' ? 'bg-yellow-600 text-black' : 'bg-green-600'); ?> 
        text-center font-bold animate-bounce">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <!-- خلفية -->
    <div class="absolute inset-0 z-0 pointer-events-none">
        <div class="absolute top-[-10%] left-[-5%] w-96 h-96 bg-brand-gold rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-pulse"></div>
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
    </div>

    <!-- كرت الدخول -->
    <div class="relative z-10 w-full max-w-4xl grid grid-cols-1 md:grid-cols-2 gap-0 glass-panel rounded-3xl overflow-hidden shadow-2xl" data-aos="fade-up">
        
        <!-- القسم الأيمن (نموذج الدخول) -->
        <div class="p-8 md:p-12 flex flex-col justify-center order-2 md:order-1">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">أهلاً بعودتك</h1>
                <p class="text-slate-500">سجل الدخول للمتابعة</p>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                <div class="group">
                    <label class="block text-xs text-slate-400 mb-2">رقم الهاتف</label>
                    <div class="relative">
                        <i class="fas fa-phone absolute right-4 top-4 text-slate-500"></i>
                        <input type="tel" name="phone" required class="w-full pr-10 pl-4 py-3 rounded-xl input-field text-white placeholder-slate-600" placeholder="77xxxxxxx">
                    </div>
                </div>

                <div class="group">
                    <label class="block text-xs text-slate-400 mb-2">كلمة المرور</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute right-4 top-4 text-slate-500"></i>
                        <input type="password" name="password" required class="w-full pr-10 pl-4 py-3 rounded-xl input-field text-white placeholder-slate-600" placeholder="••••••••">
                    </div>
                </div>

                <div class="flex justify-between items-center text-sm text-slate-400">
                    <label class="flex items-center gap-2 cursor-pointer hover:text-white">
                        <input type="checkbox" class="rounded border-slate-600 bg-slate-800 text-brand-gold focus:ring-brand-gold">
                        <span>تذكرني</span>
                    </label>
                    <a href="#" class="hover:text-brand-gold transition-colors">نسيت كلمة السر؟</a>
                </div>

                <button type="submit" class="w-full py-4 bg-gradient-to-r from-brand-gold to-yellow-600 text-brand-dark font-bold rounded-xl shadow-lg hover:shadow-[0_0_20px_rgba(251,191,36,0.5)] transform hover:-translate-y-1 transition-all duration-300">
                    تسجيل الدخول <i class="fas fa-sign-in-alt mr-2"></i>
                </button>
            </form>

            <div class="mt-8 text-center text-slate-500 text-sm">
                ليس لديك حساب؟ 
                <a href="register.php" class="text-brand-gold font-bold hover:text-white transition-colors">إنشاء حساب جديد</a>
            </div>
        </div>

        <!-- القسم الأيسر (صورة جمالية) -->
        <div class="hidden md:flex flex-col justify-center items-center p-12 relative bg-slate-900 border-r border-white/5 order-1 md:order-2">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1573855619003-97b4799dcd8b?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80')] bg-cover bg-center opacity-30 mix-blend-luminosity"></div>
            <div class="relative z-10 text-center">
                <div class="w-24 h-24 bg-brand-gold/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-brand-gold/20 animate-pulse">
                    <i class="fas fa-fingerprint text-brand-gold text-5xl"></i>
                </div>
                <h3 class="text-2xl font-bold mb-2">أمان عالي</h3>
                <p class="text-slate-400 text-sm leading-relaxed">بياناتك محمية بأحدث تقنيات التشفير. نحن نضمن لك تجربة تسوق آمنة وسلسة.</p>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>