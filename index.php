<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الصقر مول | بوابتك للمستقبل</title>
    
    <!-- خط تجوال -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-gold': '#fbbf24',
                        'brand-gold-dark': '#b45309',
                    },
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        .glass-panel {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .text-gradient {
            background: linear-gradient(to right, #fbbf24, #fcd34d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans h-screen overflow-hidden relative">

    <!-- خلفية الفيديو أو الصورة المتحركة -->
    <div class="absolute inset-0 z-0">
        <!-- صورة خلفية معتمة -->
        <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80" 
             class="w-full h-full object-cover opacity-30 scale-105 animate-pulse" style="animation-duration: 10s;">
        <div class="absolute inset-0 bg-gradient-to-t from-brand-dark via-brand-dark/90 to-brand-dark/40"></div>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="relative z-10 container mx-auto px-4 h-full flex flex-col justify-between py-10">
        
        <!-- الهيدر: الشعار فقط -->
        <header class="flex justify-center" data-aos="fade-down">
            <div class="text-4xl font-black tracking-tighter flex items-center gap-3">
                <i class="fas fa-eagle text-brand-gold text-5xl"></i>
                <span class="text-white">الصقر <span class="text-brand-gold">مول</span></span>
            </div>
        </header>

        <!-- الوسط: الرسالة الترحيبية والأزرار -->
        <main class="flex flex-col items-center text-center max-w-4xl mx-auto">
            
            <div class="mb-8 relative">
                <div class="absolute -inset-1 bg-brand-gold blur opacity-20 animate-pulse rounded-full"></div>
                <span class="relative px-4 py-1 rounded-full border border-brand-gold/30 text-brand-gold text-sm font-bold bg-brand-dark/50 backdrop-blur-md" data-aos="zoom-in">
                    💎 تجربة التسوق الأولى في اليمن
                </span>
            </div>

            <h1 class="text-5xl md:text-7xl font-black mb-6 leading-tight" data-aos="fade-up" data-aos-delay="100">
                مرحباً بك في عالم <br>
                <span class="text-gradient">الفخامة الرقمية</span>
            </h1>
            
            <p class="text-slate-400 text-lg md:text-2xl mb-12 font-light max-w-2xl leading-relaxed" data-aos="fade-up" data-aos-delay="200">
                منصة واحدة تجمع أرقى المتاجر، أحدث الماركات، وأسرع خدمة توصيل.
                <br>سجل دخولك الآن لتبدأ الرحلة.
            </p>

            <!-- الأزرار الرئيسية -->
            <div class="flex flex-col md:flex-row gap-6 w-full max-w-lg" data-aos="fade-up" data-aos-delay="300">
                
                <!-- زر إنشاء حساب -->
                <a href="register.php" class="group flex-1 relative overflow-hidden rounded-2xl bg-gradient-to-r from-brand-gold to-yellow-600 p-[2px] transition-all hover:shadow-[0_0_40px_rgba(251,191,36,0.3)]">
                    <div class="relative h-full bg-brand-dark rounded-2xl p-4 flex items-center justify-center gap-3 transition-all group-hover:bg-opacity-0">
                        <i class="fas fa-user-plus text-brand-gold group-hover:text-white text-xl transition-colors"></i>
                        <span class="font-bold text-lg text-brand-gold group-hover:text-white transition-colors">إنشاء حساب جديد</span>
                    </div>
                </a>

                <!-- زر تسجيل الدخول -->
                <a href="login.php" class="group flex-1 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 p-4 flex items-center justify-center gap-3 hover:bg-white/20 transition-all hover:border-brand-gold/50">
                    <i class="fas fa-sign-in-alt text-white text-xl"></i>
                    <span class="font-bold text-lg text-white">تسجيل الدخول</span>
                </a>
            </div>

            <!-- زر تصفح كزائر (مهم جداً لعدم خسارة الزبائن) -->
            <div class="mt-8" data-aos="fade-up" data-aos-delay="400">
                <a href="home.php" class="text-slate-500 hover:text-white transition-colors text-sm flex items-center gap-2 group">
                    <span>أريد التصفح كزائر فقط</span>
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                </a>
            </div>

        </main>

        <!-- الفوتر: حقوق الملكية -->
        <footer class="text-center text-slate-600 text-sm" data-aos="fade-up" data-aos-offset="0">
            &copy; 2024 الصقر مول. جميع الحقوق محفوظة.
        </footer>

        <!-- عناصر جمالية عائمة في الخلفية -->
        <div class="absolute top-1/4 left-10 w-32 h-32 bg-blue-600 rounded-full blur-[100px] opacity-20 animate-float"></div>
        <div class="absolute bottom-1/4 right-10 w-40 h-40 bg-brand-gold rounded-full blur-[100px] opacity-10 animate-float" style="animation-delay: 2s;"></div>

    </div>

    <!-- تفعيل الحركات -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true
        });
    </script>
</body>
</html>