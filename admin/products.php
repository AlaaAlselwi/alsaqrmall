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
    $productsCollection = $db->products;

    // 1. معالجة الإجراءات
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
        $p_id = new MongoDB\BSON\ObjectId($_POST['product_id']);
        $action = $_POST['action'];

        if ($action === 'delete') {
            $productsCollection->deleteOne(['_id' => $p_id]);
            $msg = "تم حذف المنتج بنجاح.";
            $msg_type = "error";
        } elseif ($action === 'toggle_feature') {
            // جلب الحالة الحالية وعكسها
            $product = $productsCollection->findOne(['_id' => $p_id]);
            $currentState = isset($product['is_featured']) ? $product['is_featured'] : false;
            
            $productsCollection->updateOne(
                ['_id' => $p_id],
                ['$set' => ['is_featured' => !$currentState]]
            );
            $msg = "تم تحديث حالة تمييز المنتج.";
            $msg_type = "success";
        }
    }

    // 2. البحث
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $pipeline = [];

    // Lookup لجلب بيانات المتجر
    $pipeline[] = [
        '$lookup' => [
            'from' => 'vendors',
            'localField' => 'vendor_id',
            'foreignField' => '_id',
            'as' => 'vendor'
        ]
    ];
    $pipeline[] = ['$unwind' => '$vendor']; // كل منتج له متجر واحد

    // Lookup لجلب اسم القسم (اختياري لأن القسم قد يكون null)
    $pipeline[] = [
        '$lookup' => [
            'from' => 'categories',
            'localField' => 'category_id',
            'foreignField' => '_id',
            'as' => 'category'
        ]
    ];
    // لا نستخدم unwind هنا لكي لا نحذف المنتجات التي ليس لها قسم، بل نستخدم arrayElemAt لاحقاً

    // شرط البحث
    if (!empty($search)) {
        $regex = new MongoDB\BSON\Regex($search, 'i');
        $pipeline[] = [
            '$match' => [
                '$or' => [
                    ['name' => $regex],
                    ['vendor.store_name' => $regex]
                ]
            ]
        ];
    }

    $pipeline[] = ['$sort' => ['_id' => -1]];

    $pipeline[] = [
        '$project' => [
            'id' => '$_id',
            'name' => 1,
            'image' => 1,
            'price' => 1,
            'stock' => 1,
            'is_featured' => 1,
            'views' => ['$ifNull' => ['$views', 0]],
            'store_name' => '$vendor.store_name',
            'category_name' => ['$ifNull' => [['$arrayElemAt' => ['$category.name', 0]], 'بدون قسم']]
        ]
    ];

    $products = $productsCollection->aggregate($pipeline)->toArray();

} catch(Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات | الصقر مول</title>
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
        .glass-table {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans overflow-x-hidden">

    <!-- رسائل التنبيه -->
    <?php if(!empty($msg)): ?>
    <div class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-2xl font-bold animate-bounce
        <?php echo $msg_type == 'success' ? 'bg-green-600' : 'bg-red-600'; ?>">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

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
                    <li><a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-tags"></i> الأقسام</a></li>
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg"><i class="fas fa-box-open"></i> المنتجات</a></li>
                    <li><a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-chart-line"></i> التقارير</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/20 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> إدارة المنتجات</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <div class="mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">قائمة المنتجات</h1>
                    <p class="text-slate-400">مراقبة وإدارة كافة المعروضات في المول.</p>
                </div>
                
                <!-- البحث -->
                <form class="flex gap-2 w-full md:w-auto" method="GET">
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fas fa-search text-slate-400"></i>
                        </div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="bg-slate-800 border border-slate-700 text-white text-sm rounded-lg focus:ring-brand-gold focus:border-brand-gold block w-full pr-10 p-2.5" placeholder="اسم المنتج أو المتجر...">
                    </div>
                    <button type="submit" class="bg-brand-gold text-brand-dark font-bold py-2 px-4 rounded-lg hover:bg-yellow-500 transition-colors">بحث</button>
                </form>
            </div>

            <!-- جدول المنتجات -->
            <div class="overflow-x-auto rounded-2xl glass-table shadow-2xl">
                <table class="w-full text-right">
                    <thead class="bg-slate-800/80 text-slate-300 border-b border-slate-700 uppercase">
                        <tr>
                            <th class="p-4">المنتج</th>
                            <th class="p-4">القسم</th>
                            <th class="p-4">السعر</th>
                            <th class="p-4">المخزون</th>
                            <th class="p-4">المتجر (Vendor)</th>
                            <th class="p-4 text-center">مميز؟</th>
                            <th class="p-4 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 text-sm">
                        <?php if(count($products) > 0): ?>
                            <?php foreach($products as $prod): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors group">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        
                                        <div class="w-12 h-12 rounded-lg bg-slate-700 overflow-hidden flex-shrink-0 border border-slate-600">
                                            <?php if(isset($prod['image']) && $prod['image']): ?>
                                                <img src="../<?php echo $prod['image']; ?>" class="w-full h-full object-cover" onerror="this.src='https://via.placeholder.com/150?text=No+Img'">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-slate-500"><i class="fas fa-camera"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white"><?php echo htmlspecialchars($prod['name']); ?></div>
                                            <div class="text-xs text-slate-400"><i class="far fa-eye"></i> <?php echo $prod['views']; ?> مشاهدة</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-slate-300">
                                    <span class="bg-slate-700 px-2 py-1 rounded text-xs"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                                </td>
                                <td class="p-4 font-bold text-brand-gold">
                                    <?php echo number_format($prod['price']); ?> ر.ي
                                </td>
                                <td class="p-4">
                                    <?php if($prod['stock'] > 0): ?>
                                        <span class="text-green-400 text-xs font-bold">متوفر (<?php echo $prod['stock']; ?>)</span>
                                    <?php else: ?>
                                        <span class="text-red-400 text-xs font-bold">نفذت الكمية</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <a href="vendors.php?search=<?php echo urlencode($prod['store_name']); ?>" class="text-blue-400 hover:text-blue-300 underline decoration-blue-400/30">
                                        <?php echo htmlspecialchars($prod['store_name']); ?>
                                    </a>
                                </td>
                                <td class="p-4 text-center">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_feature">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                        <button type="submit" class="transition-all hover:scale-110" title="اضغط للتبديل">
                                            <?php if(isset($prod['is_featured']) && $prod['is_featured']): ?>
                                                <i class="fas fa-star text-brand-gold text-lg shadow-glow"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-slate-600 text-lg hover:text-brand-gold"></i>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="p-4 text-center">
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟ سيختفي من الموقع.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                        <button type="submit" class="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center" title="حذف المنتج">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500">
                                    <i class="fas fa-box-open fa-3x mb-3 opacity-50"></i><br>
                                    لا توجد منتجات مسجلة حالياً.
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