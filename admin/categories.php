<?php
session_start();
require_once '../includes/db.php';

// حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $categoriesCollection = $db->categories;

    // 1. إضافة قسم جديد
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
        $name = $_POST['name'];
        $icon = $_POST['icon']; // كلاس أيقونة FontAwesome

        if (!empty($name)) {
            $categoriesCollection->insertOne([
                'name' => $name,
                'icon' => $icon,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            $msg = "تم إضافة القسم بنجاح.";
            $msg_type = "success";
        }
    }

    // 2. حذف قسم
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = new MongoDB\BSON\ObjectId($_POST['category_id']);
        $categoriesCollection->deleteOne(['_id' => $id]);
        $msg = "تم حذف القسم.";
        $msg_type = "error";
    }

    // 3. جلب الأقسام
    $categories = $categoriesCollection->find([], ['sort' => ['_id' => -1]])->toArray();

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام | الصقر مول</title>
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
    </style>
</head>
<body class="bg-brand-dark text-white font-sans overflow-x-hidden">

    <div class="flex min-h-screen">
        
        <!-- الشريط الجانبي -->
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
                    <!-- رابط الصفحة الحالية -->
                    <li><a href="categories.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg shadow-yellow-500/20"><i class="fas fa-tags"></i> الأقسام</a></li>
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box-open"></i> المنتجات</a></li>
                    <li><a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-chart-line"></i> التقارير</a></li>
                </ul>
            </nav>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> الأقسام</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <h1 class="text-3xl font-bold mb-8">أقسام الموقع</h1>

            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-xl font-bold text-center <?php echo $msg_type == 'success' ? 'bg-green-600/20 text-green-500' : 'bg-red-600/20 text-red-500'; ?>">
                <?php echo $msg; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- نموذج الإضافة -->
                <div class="glass-card rounded-2xl p-6 h-fit">
                    <h3 class="font-bold text-lg mb-4 text-brand-gold">إضافة قسم جديد</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">اسم القسم</label>
                            <input type="text" name="name" required class="w-full p-3 rounded-xl input-dark" placeholder="مثال: إلكترونيات">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">أيقونة (FontAwesome Class)</label>
                            <input type="text" name="icon" class="w-full p-3 rounded-xl input-dark" placeholder="fas fa-mobile-alt" dir="ltr">
                            <p class="text-xs text-slate-500 mt-1">يمكنك تركها فارغة أو استخدام كلاسات FontAwesome</p>
                        </div>
                        <button type="submit" class="w-full bg-brand-gold hover:bg-yellow-500 text-brand-dark font-bold py-3 rounded-xl transition-all">إضافة</button>
                    </form>
                </div>

                <!-- جدول الأقسام -->
                <div class="md:col-span-2 glass-card rounded-2xl overflow-hidden">
                    <table class="w-full text-right">
                        <thead class="bg-slate-800/50 text-slate-400 text-sm">
                            <tr>
                                <th class="p-4">الاسم</th>
                                <th class="p-4 text-center">الأيقونة</th>
                                <th class="p-4 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach($categories as $cat): ?>
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-4 font-bold"><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td class="p-4 text-center text-brand-gold text-xl">
                                    <i class="<?php echo htmlspecialchars(isset($cat['icon']) && $cat['icon'] ? $cat['icon'] : 'fas fa-box'); ?>"></i>
                                </td>
                                <td class="p-4 text-center">
                                    <form method="POST" onsubmit="return confirm('حذف القسم قد يؤثر على المنتجات المرتبطة به. هل أنت متأكد؟');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['_id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-400 bg-red-500/10 p-2 rounded-lg transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>
</body>
</html>