<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// التأكد من المسار الصحيح لملف الاتصال
// بما أن هذا الملف سيتم تضمينه داخل ملفات في المجلد customer، فإن المسار لقاعدة البيانات هو ../includes/db.php
// لكن للتأكد نستخدم __DIR__
require_once __DIR__ . '/../../includes/db.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'الصقر مول | Alsaqrmall'; ?></title>
    
    <!-- خط تجوال العصري -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    
    <!-- مكتبة Tailwind CSS للتصميم الحديث -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- مكتبة FontAwesome للأيقونات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- مكتبة AOS للحركات -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a', /* كحلي غامق جداً */
                        'brand-gold': '#fbbf24', /* ذهبي */
                        'brand-accent': '#3b82f6', /* أزرق نيون */
                    },
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        /* تأثير الزجاج */
        .glass-nav {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* تأثير النصوص المتدرجة */
        .text-gradient {
            background: linear-gradient(to right, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* كلاس لإخفاء شريط التمرير وجعله جميلاً */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* تأثير البطاقات */
        .product-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: #fbbf24;
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans overflow-x-hidden">

    <!-- الناف بار (عائم وشفاف) -->
    <nav class="fixed w-full z-50 glass-nav transition-all duration-300" id="navbar">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <!-- الشعار -->
            <a href="index.php" class="text-3xl font-black tracking-tighter flex items-center gap-2">
                <i class="fas fa-eagle text-brand-gold"></i>
                <span class="text-white">الصقر <span class="text-brand-gold">مول</span></span>
            </a>

            <!-- البحث (مخفي في الموبايل، ظاهر في الديسكتوب) -->
            <div class="hidden md:flex flex-1 mx-10 relative group">
                <input type="text" placeholder="ابحث عن الفخامة..." 
                    class="w-full bg-slate-800/50 border border-slate-700 rounded-full py-2 px-6 focus:outline-none focus:border-brand-gold focus:ring-1 focus:ring-brand-gold transition-all text-sm">
                <button class="absolute left-3 top-2 text-slate-400 group-hover:text-brand-gold transition-colors">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            <!-- الأيقونات -->
            <div class="flex items-center gap-6">
                <!-- أيقونة السلة -->
                <a href="cart.php" class="relative cursor-pointer group text-white hover:text-brand-gold transition">
                    <i class="fas fa-shopping-bag text-xl"></i>
                    <?php 
                        $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
                    ?>
                    <?php if($cart_count > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-brand-gold text-brand-dark text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center animate-bounce"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- قائمة المستخدم المسجل -->
                    <div class="hidden md:flex items-center gap-3">
                        <a href="profile.php" class="text-sm text-slate-300 hover:text-brand-gold transition-colors flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-brand-gold text-xs border border-slate-600">
                                <i class="fas fa-user"></i>
                            </div>
                            مرحباً، <?php echo isset($_SESSION['user_name']) ? explode(' ', $_SESSION['user_name'])[0] : 'ضيف'; ?>
                        </a>
                        <a href="../logout.php" class="bg-slate-700 hover:bg-red-600 text-white px-4 py-1.5 rounded-full text-xs transition-colors">خروج</a>
                    </div>
                <?php else: ?>
                    <!-- زر الدخول -->
                    <a href="../login.php" class="bg-gradient-to-r from-brand-gold to-orange-500 text-brand-dark font-bold py-2 px-6 rounded-full hover:shadow-lg hover:shadow-orange-500/20 transition-all transform hover:scale-105 hidden md:block">
                        دخول
                    </a>
                <?php endif; ?>
                
                <!-- زر القائمة للموبايل -->
                <button class="md:hidden text-2xl text-white">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- قائمة الموبايل (Mobile Menu) -->
    <div id="mobile-menu" class="fixed inset-0 z-40 bg-brand-dark/95 backdrop-blur-xl transform translate-x-full transition-transform duration-300 md:hidden flex flex-col justify-center items-center space-y-8">
        <button id="close-menu" class="absolute top-6 left-6 text-white text-3xl">
            <i class="fas fa-times"></i>
        </button>
        
        <a href="index.php" class="text-2xl font-bold text-white hover:text-brand-gold transition-colors">الرئيسية</a>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="text-2xl font-bold text-white hover:text-brand-gold transition-colors">الملف الشخصي</a>
            <a href="cart.php" class="text-2xl font-bold text-white hover:text-brand-gold transition-colors">
                السلة 
                <?php if($cart_count > 0): ?>
                    <span class="bg-brand-gold text-brand-dark text-sm px-2 py-1 rounded-full ml-2"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="../logout.php" class="text-xl text-red-400 border border-red-400/30 px-6 py-2 rounded-full hover:bg-red-400/10 transition-colors">تسجيل الخروج</a>
        <?php else: ?>
            <a href="../login.php" class="text-2xl font-bold text-brand-gold border-2 border-brand-gold px-8 py-2 rounded-full hover:bg-brand-gold hover:text-brand-dark transition-colors">تسجيل الدخول</a>
            <a href="../register.php" class="text-xl text-slate-300">إنشاء حساب</a>
        <?php endif; ?>
    </div>

    <script>
        const btn = document.querySelector('button.md\\:hidden');
        const menu = document.getElementById('mobile-menu');
        const closeBtn = document.getElementById('close-menu');

        if(btn && menu && closeBtn) {
            btn.addEventListener('click', () => {
                menu.classList.remove('translate-x-full');
            });

            closeBtn.addEventListener('click', () => {
                menu.classList.add('translate-x-full');
            });
        }
    </script>
    
    <!-- مسافة عشان الناف بار المثبت ما يغطي المحتوى -->
    <div class="h-20"></div>
