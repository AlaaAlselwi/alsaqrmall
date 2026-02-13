<?php
session_start();
require_once '../includes/db.php'; // استدعاء ملف الاتصال

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    
    // جلب معرف المتجر
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $vendor = $db->vendors->findOne(['user_id' => $user_id]);

    if (!$vendor) {
        die("خطأ: لم يتم العثور على حساب التاجر.");
    }

    $vendor_id = $vendor['_id'];
    $store_name = $vendor['store_name'];

    // 2. معالجة طلب الحذف
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $del_id = new MongoDB\BSON\ObjectId($_POST['delete_id']);
        
        // التأكد أن المنتج يتبع لهذا التاجر فعلاً
        $prod = $db->products->findOne(['_id' => $del_id, 'vendor_id' => $vendor_id]);

        if ($prod) {
            // حذف المنتج
            $db->products->deleteOne(['_id' => $del_id]);
            
            // (اختياري) حذف الصورة من السيرفر
            if (isset($prod['image']) && file_exists("../" . $prod['image'])) {
                unlink("../" . $prod['image']);
            }
            
            $msg = "تم حذف المنتج بنجاح.";
            $msg_type = "success";
        }
    }

    // 3. جلب منتجات هذا التاجر فقط
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $pipeline = [];
    
    // تصفية حسب التاجر
    $matchStage = ['vendor_id' => $vendor_id];

    // إضافة شرط البحث إن وجد
    if (!empty($search)) {
        $matchStage['name'] = ['$regex' => $search, '$options' => 'i'];
    }
    
    $pipeline[] = ['$match' => $matchStage];

    // جلب اسم القسم
    $pipeline[] = [
        '$lookup' => [
            'from' => 'categories',
            'localField' => 'category_id',
            'foreignField' => '_id',
            'as' => 'category'
        ]
    ];
    $pipeline[] = [
        '$unwind' => [
            'path' => '$category',
            'preserveNullAndEmptyArrays' => true // لضمان ظهور المنتج حتى لو القسم محذوف
        ]
    ];

    // ترتيب تنازلي
    $pipeline[] = ['$sort' => ['_id' => -1]];

    $products = $db->products->aggregate($pipeline)->toArray();

} catch(Exception $e) {
    die("خطأ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منتجاتي | <?php echo htmlspecialchars($store_name); ?></title>
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
        .glass-table {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        body::-webkit-scrollbar { width: 8px; }
        body::-webkit-scrollbar-track { background: #0f172a; }
        body::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 4px; }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans overflow-x-hidden">

    <div class="flex min-h-screen">
        
        <!-- الشريط الجانبي -->
        <aside class="w-64 bg-brand-sidebar border-l border-slate-800 hidden md:flex flex-col fixed h-full z-20">
            <div class="h-24 flex flex-col items-center justify-center border-b border-slate-800 p-4">
                <div class="text-xl font-bold text-white mb-1"><?php echo htmlspecialchars($store_name); ?></div>
                <div class="text-xs text-green-400 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> متصل الآن
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto py-6">
                <ul class="space-y-2 px-4">
                    <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-home"></i> نظرة عامة</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 bg-brand-accent text-white rounded-xl font-bold shadow-lg shadow-blue-500/20"><i class="fas fa-box"></i> منتجاتي</a></li>
                    <li><a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-shopping-bag"></i> الطلبات</a></li>
                    <li><a href="wallet.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-wallet"></i> المحفظة</a></li>
                    <li><a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-cog"></i> إعدادات المتجر</a></li>
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

            <!-- التنبيهات -->
            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo $msg_type == 'success' ? 'bg-green-600/20 text-green-500 border-green-500/30' : 'bg-red-600/20 text-red-500 border-red-500/30'; ?> border font-bold text-center">
                <?php echo $msg; ?>
            </div>
            <?php endif; ?>

            <div class="mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">إدارة المخزون</h1>
                    <p class="text-slate-400">لديك <span class="text-brand-gold font-bold"><?php echo count($products); ?></span> منتجات في متجرك.</p>
                </div>
                
                <div class="flex gap-3 w-full md:w-auto">
                    <!-- نموذج البحث -->
                    <form class="relative flex-1 md:w-64">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl pl-4 pr-10 py-2.5 focus:border-brand-accent focus:ring-1 focus:ring-brand-accent transition-all" placeholder="بحث عن منتج...">
                        <button type="submit" class="absolute left-3 top-3 text-slate-400 hover:text-white"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <!-- زر إضافة جديد -->
                    <a href="add_product.php" class="bg-brand-accent hover:bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-blue-500/20 flex items-center gap-2 transition-all">
                        <i class="fas fa-plus"></i> <span class="hidden md:inline">إضافة منتج</span>
                    </a>
                </div>
            </div>

            <!-- جدول المنتجات -->
            <div class="overflow-x-auto rounded-2xl glass-table shadow-2xl">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-800/80 text-slate-300 border-b border-slate-700">
                        <tr>
                            <th class="p-4">تفاصيل المنتج</th>
                            <th class="p-4">القسم</th>
                            <th class="p-4">السعر</th>
                            <th class="p-4">المخزون</th>
                            <th class="p-4 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php if(count($products) > 0): ?>
                            <?php foreach($products as $prod): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors group">
                                <td class="p-4">
                                    <div class="flex items-center gap-4">
                                        <div class="w-16 h-16 rounded-xl bg-slate-700 overflow-hidden flex-shrink-0 border border-slate-600">
                                            <?php if(isset($prod['image']) && $prod['image']): ?>
                                                <img src="../<?php echo $prod['image']; ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-slate-500"><i class="fas fa-camera text-xl"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white text-base mb-1"><?php echo htmlspecialchars($prod['name']); ?></div>
                                            <div class="text-xs text-slate-400 line-clamp-1"><?php echo htmlspecialchars(substr($prod['description'] ?? '', 0, 50)); ?>...</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-slate-300">
                                    <span class="bg-slate-700/50 px-2 py-1 rounded border border-slate-600">
                                        <?php echo htmlspecialchars($prod['category']['name'] ?? 'عام'); ?>
                                    </span>
                                </td>
                                <td class="p-4 font-bold text-brand-gold text-lg">
                                    <?php echo number_format($prod['price']); ?>
                                </td>
                                <td class="p-4">
                                    <?php if($prod['stock'] > 5): ?>
                                        <span class="text-green-400 font-bold"><?php echo $prod['stock']; ?> قطعة</span>
                                    <?php elseif($prod['stock'] > 0): ?>
                                        <span class="text-yellow-500 font-bold">باقي <?php echo $prod['stock']; ?> فقط!</span>
                                    <?php else: ?>
                                        <span class="text-red-500 font-bold bg-red-500/10 px-2 py-1 rounded">نفذت الكمية</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex justify-center items-center gap-3">
                                        <!-- زر تعديل -->
                                        <a href="edit_product.php?id=<?php echo $prod['_id']; ?>" class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 hover:bg-blue-500 hover:text-white transition-all flex items-center justify-center" title="تعديل">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        
                                        <!-- زر حذف -->
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟ لا يمكن التراجع.');">
                                            <input type="hidden" name="delete_id" value="<?php echo $prod['_id']; ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center" title="حذف">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-500">
                                        <i class="fas fa-box-open text-6xl mb-4 opacity-50"></i>
                                        <h3 class="text-xl font-bold text-white mb-2">المخزن فارغ!</h3>
                                        <p class="mb-6">لم تقم بإضافة أي منتجات حتى الآن.</p>
                                        <a href="add_product.php" class="bg-brand-accent hover:bg-blue-600 text-white px-6 py-2 rounded-xl font-bold transition-all">
                                            إضافة أول منتج
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

</body>
</html>