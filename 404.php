<?php
// إعداد كود الاستجابة HTTP
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صفحة غير موجودة | 404</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-gold': '#fbbf24',
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
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
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 191, 36, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(15, 23, 42, 1) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4 overflow-hidden relative">

    <!-- الخلفية المتحركة -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-10 left-10 text-slate-800 text-9xl opacity-20 animate-float" style="animation-delay: 0s;">404</div>
        <div class="absolute bottom-20 right-20 text-slate-800 text-9xl opacity-20 animate-float" style="animation-delay: 2s;">?</div>
        <div class="absolute top-1/2 left-1/3 text-brand-gold blur-3xl opacity-10 w-96 h-96 rounded-full animate-pulse-slow"></div>
    </div>

    <!-- بطاقة الخطأ -->
    <div class="glass-card max-w-lg w-full rounded-3xl p-8 md:p-12 text-center relative z-10 border-t border-brand-gold/30">
        
        <div class="flex justify-center mb-8">
            <div class="w-32 h-32 bg-slate-800/50 rounded-full flex items-center justify-center relative group">
                <i class="fas fa-search text-6xl text-slate-600 group-hover:text-brand-gold transition-colors duration-500"></i>
                <div class="absolute -top-2 -right-2 bg-red-500 rounded-full p-2 animate-bounce">
                    <i class="fas fa-exclamation text-white"></i>
                </div>
            </div>
        </div>

        <h1 class="text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-brand-gold to-yellow-600 mb-4 font-sans">404</h1>
        
        <h2 class="text-2xl font-bold mb-4">عذراً، الصفحة غير موجودة</h2>
        
        <p class="text-slate-400 mb-8 leading-relaxed">
            يبدو أنك قد ضللت الطريق في أروقة الصقر مول.<br>
            الصفحة التي تبحث عنها قد تكون حذفت أو تم تغيير رابطها.
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/alsaqrmall/index.php" class="bg-brand-gold text-brand-dark font-bold py-3 px-8 rounded-xl hover:bg-yellow-500 transition-all hover:scale-105 hover:shadow-lg hover:shadow-yellow-500/20 flex items-center justify-center gap-2">
                <i class="fas fa-home"></i> العودة للرئيسية
            </a>
            
            <button onclick="history.back()" class="bg-slate-700 text-white font-bold py-3 px-8 rounded-xl hover:bg-slate-600 transition-all hover:scale-105 flex items-center justify-center gap-2">
                <i class="fas fa-arrow-right"></i> السابق
            </button>
        </div>

    </div>

</body>
</html>
