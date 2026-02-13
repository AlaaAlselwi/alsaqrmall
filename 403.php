<?php
// إعداد كود الاستجابة HTTP
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وصول ممنوع | 403</title>
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
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #0f172a;
            background-image: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4">

    <div class="glass-card max-w-lg w-full rounded-3xl p-8 md:p-12 text-center relative border-t-2 border-red-500">
        
        <div class="flex justify-center mb-8">
            <div class="w-24 h-24 bg-red-500/10 rounded-full flex items-center justify-center animate-pulse">
                <i class="fas fa-lock text-5xl text-red-500"></i>
            </div>
        </div>

        <h1 class="text-5xl font-black text-white mb-2">403</h1>
        <h2 class="text-2xl font-bold mb-4 text-red-400">نأسف، الوصول ممنوع</h2>
        
        <p class="text-slate-400 mb-8">
            ليس لديك الصلاحية للدخول إلى هذه الصفحة.<br>
            إذا كنت تعتقد أن هذا خطأ، يرجى تسجيل الدخول بحساب ذو صلاحيات أعلى.
        </p>

        <div class="space-y-3">
            <a href="/alsaqrmall/login.php" class="block w-full bg-brand-gold text-brand-dark font-bold py-3 px-8 rounded-xl hover:bg-yellow-500 transition-colors">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </a>
            <a href="/alsaqrmall/index.php" class="block w-full bg-slate-700 text-white font-bold py-3 px-8 rounded-xl hover:bg-slate-600 transition-colors">
                <i class="fas fa-home"></i> الرئيسية
            </a>
        </div>

    </div>

</body>
</html>
