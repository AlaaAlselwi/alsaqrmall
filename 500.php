<?php
// إعداد كود الاستجابة HTTP
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطأ في الخادم | 500</title>
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
        body { background-color: #0f172a; }
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4">

    <div class="glass-card max-w-lg w-full rounded-3xl p-8 md:p-12 text-center border-t-2 border-brand-gold">
        
        <div class="flex justify-center mb-8">
            <i class="fas fa-cogs text-6xl text-slate-600 animate-spin-slow" style="animation-duration: 3s;"></i>
        </div>

        <h1 class="text-5xl font-black mb-2">500</h1>
        <h2 class="text-2xl font-bold mb-4 text-slate-300">حدث خطأ داخلي</h2>
        
        <p class="text-slate-400 mb-8">
            واجه الخادم مشكلة غير متوقعة.<br>
            نحن نعمل على إصلاحها حالياً. يرجى المحاولة مرة أخرى لاحقاً.
        </p>

        <button onclick="location.reload()" class="bg-brand-gold text-brand-dark font-bold py-3 px-8 rounded-xl hover:bg-yellow-500 transition-colors flex items-center justify-center gap-2 w-full mb-3">
            <i class="fas fa-sync-alt"></i> تحديث الصفحة
        </button>
        
        <a href="/alsaqrmall/index.php" class="block w-full text-slate-400 hover:text-white transition-colors underline">
            العودة للرئيسية
        </a>

    </div>

</body>
</html>
