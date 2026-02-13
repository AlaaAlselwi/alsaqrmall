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
    $vendorsCollection = $db->vendors;
    $productsCollection = $db->products;
    $usersCollection = $db->users;

    // 1. معالجة الإجراءات
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_id'])) {
        $v_id = new MongoDB\BSON\ObjectId($_POST['vendor_id']);
        $action = $_POST['action'];

        if ($action === 'suspend') {
            $vendorsCollection->updateOne(['_id' => $v_id], ['$set' => ['status' => 'suspended']]);
            $msg = "تم تجميد حساب المتجر.";
            $msg_type = "warning";
        } elseif ($action === 'activate') {
            $vendorsCollection->updateOne(['_id' => $v_id], ['$set' => ['status' => 'active']]);
            $msg = "تم إعادة تفعيل المتجر بنجاح.";
            $msg_type = "success";
        } elseif ($action === 'delete') {
            // حذف المتجر ومنتجاته
            // 1. حذف المنتجات
            $productsCollection->deleteMany(['vendor_id' => $v_id]);
            // 2. حذف المتجر
            $vendorsCollection->deleteOne(['_id' => $v_id]);
            
            $msg = "تم حذف المتجر وجميع بياناته نهائياً.";
            $msg_type = "error";
        }
    }

    // 2. البحث والفلترة
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

    $matchStage = [];

    // شرط الفلترة
    if ($filter !== 'all') {
        $matchStage['status'] = $filter;
    }

    // بناء الـ Pipeline
    $pipeline = [];
    
    // أولاً: تصفية حسب الحالة إذا وجدت
    if (!empty($matchStage)) {
        $pipeline[] = ['$match' => $matchStage];
    }

    // ثانياً: Lookup لجلب بيانات المالك (Users)
    $pipeline[] = [
        '$lookup' => [
            'from' => 'users',
            'localField' => 'user_id',
            'foreignField' => '_id',
            'as' => 'owner'
        ]
    ];
    $pipeline[] = ['$unwind' => '$owner'];

    // ثالثاً: Lookup لجلب عدد المنتجات (Products Count)
    // الطريقة الأفضل هي استخدام lookup مع pipeline فرعي للعد
    $pipeline[] = [
        '$lookup' => [
            'from' => 'products',
            'let' => ['vendorId' => '$_id'],
            'pipeline' => [
                ['$match' => ['$expr' => ['$eq' => ['$vendor_id', '$$vendorId']]]],
                ['$count' => 'count']
            ],
            'as' => 'product_stats'
        ]
    ];

    // رابعاً: شرط البحث (Search)
    // البحث هنا يحتاج أن يكون بعد الـ lookup لأننا نبحث في حقول المالك أيضاً
    if (!empty($search)) {
        $regex = new MongoDB\BSON\Regex($search, 'i');
        $pipeline[] = [
            '$match' => [
                '$or' => [
                    ['store_name' => $regex],
                    ['owner.phone' => $regex],
                    ['owner.first_name' => $regex],
                    ['owner.last_name' => $regex]
                ]
            ]
        ];
    }

    // خامساً: الترتيب وتشكيل البيانات النهائية
    $pipeline[] = ['$sort' => ['_id' => -1]];
    $pipeline[] = [
        '$project' => [
            'id' => '$_id',
            'store_name' => 1,
            'store_type' => 1,
            'status' => 1,
            'logo' => 1,
            'documents' => 1, // جلب الوثائق
            'first_name' => '$owner.first_name',
            'last_name' => '$owner.last_name',
            'phone' => '$owner.phone',
            'product_count' => ['$ifNull' => [['$arrayElemAt' => ['$product_stats.count', 0]], 0]]
        ]
    ];

    $vendors = $vendorsCollection->aggregate($pipeline)->toArray();

} catch(Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المتاجر | الصقر مول</title>
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
        <?php echo $msg_type == 'success' ? 'bg-green-600' : ($msg_type == 'warning' ? 'bg-yellow-600 text-black' : 'bg-red-600'); ?>">
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <div class="flex min-h-screen">
        
        <!-- Sidebar -->
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
                    <li><a href="vendors.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg"><i class="fas fa-store"></i> إدارة المتاجر</a></li>
                    <li><a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-tags"></i> الأقسام</a></li>
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box-open"></i> المنتجات</a></li>
                    <li><a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-chart-line"></i> التقارير</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/20 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> إدارة المتاجر</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <div class="mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">كافة المتاجر</h1>
                    <p class="text-slate-400">إدارة ومراقبة جميع المتاجر المسجلة في النظام.</p>
                </div>
                
                <!-- Filter & Search -->
                <form class="flex gap-2 w-full md:w-auto" method="GET">
                    <select name="filter" class="bg-slate-800 border border-slate-700 text-white text-sm rounded-lg focus:ring-brand-gold focus:border-brand-gold block p-2.5">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>الكل</option>
                        <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="suspended" <?php echo $filter == 'suspended' ? 'selected' : ''; ?>>مجمد</option>
                        <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                    </select>
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fas fa-search text-slate-400"></i>
                        </div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="bg-slate-800 border border-slate-700 text-white text-sm rounded-lg focus:ring-brand-gold focus:border-brand-gold block w-full pr-10 p-2.5" placeholder="بحث باسم المتجر أو الهاتف...">
                    </div>
                    <button type="submit" class="bg-brand-gold text-brand-dark font-bold py-2 px-4 rounded-lg hover:bg-yellow-500 transition-colors">تطبيق</button>
                </form>
            </div>

            <!-- Vendors Table -->
            <div class="overflow-x-auto rounded-2xl glass-table shadow-2xl">
                <table class="w-full text-right">
                    <thead class="bg-slate-800/80 text-slate-300 border-b border-slate-700 uppercase">
                        <tr>
                            <th class="p-4">المعرف</th>
                            <th class="p-4">معلومات المتجر</th>
                            <th class="p-4">المالك</th>
                            <th class="p-4 text-center">المنتجات</th>
                            <th class="p-4 text-center">الحالة</th>
                            <th class="p-4 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 text-sm">
                        <?php if(count($vendors) > 0): ?>
                            <?php foreach($vendors as $vendor): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors group">
                                <td class="p-4 text-slate-500">#<?php echo substr((string)$vendor['id'], -6); ?></td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-slate-700 flex items-center justify-center text-brand-gold text-xl overflow-hidden">
                                            <?php if(isset($vendor['logo']) && $vendor['logo']): ?>
                                                <img src="../<?php echo $vendor['logo']; ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-store"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white text-base"><?php echo htmlspecialchars($vendor['store_name']); ?></div>
                                            <div class="text-slate-400 text-xs"><?php echo htmlspecialchars($vendor['store_type']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="text-slate-200"><?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></div>
                                    <div class="text-brand-gold text-xs font-mono"><?php echo htmlspecialchars($vendor['phone']); ?></div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="bg-slate-700 text-white px-3 py-1 rounded-full text-xs font-bold">
                                        <?php echo $vendor['product_count']; ?>
                                    </span>
                                    <?php if(isset($vendor['documents']['profile_pdf'])): ?>
                                        <a href="../<?php echo htmlspecialchars($vendor['documents']['profile_pdf']); ?>" target="_blank" class="mr-2 text-blue-400 hover:text-white text-xs underline">
                                            ملف PDF
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <?php 
                                        $status_color = 'bg-slate-600';
                                        $status_text = 'غير معروف';
                                        if($vendor['status'] == 'active') { $status_color = 'bg-green-500/20 text-green-500 border-green-500/30'; $status_text = 'نشط'; }
                                        elseif($vendor['status'] == 'suspended') { $status_color = 'bg-red-500/20 text-red-500 border-red-500/30'; $status_text = 'مجمد'; }
                                        elseif($vendor['status'] == 'pending') { $status_color = 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30'; $status_text = 'انتظار'; }
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs border <?php echo $status_color; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex justify-center items-center gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                        
                                        <?php if($vendor['status'] == 'active'): ?>
                                            <form method="POST" onsubmit="return confirm('تجميد الحساب سيمنع التاجر من الدخول. هل أنت متأكد؟');">
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-yellow-500/10 text-yellow-500 hover:bg-yellow-500 hover:text-black transition-all flex items-center justify-center" title="تجميد الحساب">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php elseif($vendor['status'] == 'suspended'): ?>
                                            <form method="POST" onsubmit="return confirm('هل تريد إعادة تفعيل هذا المتجر؟');">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-green-500/10 text-green-500 hover:bg-green-500 hover:text-white transition-all flex items-center justify-center" title="إعادة تفعيل">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" onsubmit="return confirm('تحذير خطير: حذف المتجر سيؤدي لحذف كل منتجاته وطلباته. لا يمكن التراجع! هل أنت متأكد؟');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center" title="حذف نهائي">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">
                                    <i class="fas fa-search fa-3x mb-3 opacity-50"></i><br>
                                    لا توجد متاجر تطابق بحثك.
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