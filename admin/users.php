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
    $usersCollection = $db->users;
    $ordersCollection = $db->orders;

    // 1. معالجة الإجراءات
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
        $u_id = new MongoDB\BSON\ObjectId($_POST['user_id']);
        $action = $_POST['action'];

        if ($action === 'delete') {
            // حذف المستخدم
            $deleteResult = $usersCollection->deleteOne(['_id' => $u_id, 'role' => 'customer']);
            
            if ($deleteResult->getDeletedCount() > 0) {
                $ordersCollection->deleteMany(['customer_id' => $u_id]);
                $msg = "تم حذف حساب المستخدم بنجاح.";
                $msg_type = "error";
            } else {
                $msg = "فشل الحذف أو المستخدم غير موجود.";
                $msg_type = "warning";
            }
        } elseif ($action === 'approve_phone') {
            // الموافقة على تغيير الرقم
            $user = $usersCollection->findOne(['_id' => $u_id]);
            if (isset($user['pending_update']) && $user['pending_update']['type'] === 'phone') {
                $new_phone = $user['pending_update']['value'];
                $usersCollection->updateOne(
                    ['_id' => $u_id],
                    [
                        '$set' => ['phone' => $new_phone],
                        '$unset' => ['pending_update' => ""]
                    ]
                );
                $msg = "تم تحديث رقم الهاتف بنجاح.";
                $msg_type = "success";
            }
        } elseif ($action === 'reject_phone') {
            // رفض تغيير الرقم
            $usersCollection->updateOne(
                ['_id' => $u_id],
                ['$unset' => ['pending_update' => ""]]
            );
            $msg = "تم رفض طلب تغيير الرقم.";
            $msg_type = "warning";
        }
    }

    // 2. البحث والفلترة
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $pipeline = [];

    // شرط: فقط الزملاء (Customers)
    $matchStage = ['role' => 'customer'];

    if (!empty($search)) {
        $regex = new MongoDB\BSON\Regex($search, 'i');
        $matchStage['$or'] = [
            ['first_name' => $regex],
            ['last_name' => $regex],
            ['phone' => $regex]
        ];
    }
    
    $pipeline[] = ['$match' => $matchStage];

    // Lookup لجلب إحصائيات الطلبات
    // نحسب عدد الطلبات وإجمالي المبالغ من مجموعة Orders
    $pipeline[] = [
        '$lookup' => [
            'from' => 'orders',
            'let' => ['customerId' => '$_id'],
            'pipeline' => [
                ['$match' => ['$expr' => ['$eq' => ['$customer_id', '$$customerId']]]],
                ['$group' => [
                    '_id' => null,
                    'count' => ['$sum' => 1],
                    'total_spent' => ['$sum' => '$total_amount']
                ]]
            ],
            'as' => 'order_stats'
        ]
    ];

    $pipeline[] = ['$sort' => ['_id' => -1]];
    
    // تشكيل البيانات النهائية
    $pipeline[] = [
        '$project' => [
            'id' => '$_id',
            'first_name' => 1,
            'last_name' => 1,
            'phone' => 1,
            'created_at' => 1,
            'pending_update' => 1, // جلب حقل التحديثات المعلقة
            'order_count' => ['$ifNull' => [['$arrayElemAt' => ['$order_stats.count', 0]], 0]],
            'total_spent' => ['$ifNull' => [['$arrayElemAt' => ['$order_stats.total_spent', 0]], 0]]
        ]
    ];

    $users = $usersCollection->aggregate($pipeline)->toArray();

    // جلب طلبات تحديث أرقام الهاتف المعلقة
    $pending_updates = $usersCollection->find(['pending_update' => ['$exists' => true]])->toArray();

} catch(Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين | الصقر مول</title>
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
        body::-webkit-scrollbar { width: 8px; }
        body::-webkit-scrollbar-track { background: #0f172a; }
        body::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
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
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box-open"></i> المنتجات</a></li>
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
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> إدارة المستخدمين</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <div class="mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2">قائمة الزبائن</h1>
                    <p class="text-slate-400">إدارة حسابات الزبائن والاطلاع على نشاطهم.</p>
                </div>
                
                <!-- البحث -->
                <form class="flex gap-2 w-full md:w-auto" method="GET">
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fas fa-search text-slate-400"></i>
                        </div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="bg-slate-800 border border-slate-700 text-white text-sm rounded-lg focus:ring-brand-gold focus:border-brand-gold block w-full pr-10 p-2.5" placeholder="بحث بالاسم أو الهاتف...">
                    </div>
                    <button type="submit" class="bg-brand-gold text-brand-dark font-bold py-2 px-4 rounded-lg hover:bg-yellow-500 transition-colors">بحث</button>
                </form>
            </div>

            <!-- جدول طلبات تحديث البيانات -->
            <?php if(count($pending_updates) > 0): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 text-yellow-500 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i> طلبات تغيير أرقام الهواتف
                </h2>
                <div class="overflow-x-auto rounded-2xl glass-table border border-yellow-500/30">
                    <table class="w-full text-right">
                        <thead class="bg-yellow-500/10 text-yellow-200">
                            <tr>
                                <th class="p-4">المستخدم</th>
                                <th class="p-4">الرقم الحالي</th>
                                <th class="p-4">الرقم الجديد المطلوب</th>
                                <th class="p-4 text-center">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach($pending_updates as $u): ?>
                            <tr>
                                <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                <td class="p-4 text-slate-400"><?php echo htmlspecialchars($u['phone']); ?></td>
                                <td class="p-4 text-yellow-400 font-bold"><?php echo htmlspecialchars($u['pending_update']['value']); ?></td>
                                <td class="p-4 flex justify-center gap-2">
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من الموافقة على تغيير الرقم؟');">
                                        <input type="hidden" name="action" value="approve_phone">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-lg text-xs">موافقة</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من رفض الطلب؟');">
                                        <input type="hidden" name="action" value="reject_phone">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs">رفض</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- جدول المستخدمين -->
            <div class="overflow-x-auto rounded-2xl glass-table shadow-2xl">
                <table class="w-full text-right">
                    <thead class="bg-slate-800/80 text-slate-300 border-b border-slate-700 uppercase">
                        <tr>
                            <th class="p-4">#</th>
                            <th class="p-4">الاسم الكامل</th>
                            <th class="p-4">رقم الهاتف</th>
                            <th class="p-4 text-center">عدد الطلبات</th>
                            <th class="p-4 text-center">إجمالي الإنفاق</th>
                            <th class="p-4">تاريخ التسجيل</th>
                            <th class="p-4 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 text-sm">
                        <?php if(count($users) > 0): ?>
                            <?php foreach($users as $user): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors group">
                                <td class="p-4 text-slate-500"><?php echo substr((string)$user['id'], -6); ?></td>
                                <td class="p-4 font-bold text-white">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-brand-gold"><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td class="p-4 text-center">
                                    <span class="bg-slate-700 px-2 py-1 rounded text-xs"><?php echo $user['order_count']; ?> طلب</span>
                                </td>
                                <td class="p-4 text-center text-green-400 font-bold">
                                    <?php echo number_format($user['total_spent']); ?> ر.ي
                                </td>
                                <td class="p-4 text-slate-400">
                                    <?php 
                                    $date = isset($user['created_at']) ? $user['created_at']->toDateTime()->format('Y/m/d') : '-';
                                    echo $date; 
                                    ?>
                                </td>
                                <td class="p-4 text-center">
                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-500 hover:bg-red-500/10 p-2 rounded-lg transition-all" title="حذف الحساب">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500">
                                    <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i><br>
                                    لا يوجد مستخدمين يطابقون البحث.
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