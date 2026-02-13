<?php
session_start();
require_once '../includes/db.php';

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();
    $vendorsCollection = $db->vendors;
    $usersCollection = $db->users;
    $ordersCollection = $db->orders;

    // 2. معالجة طلبات الموافقة/الرفض
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_id']) && isset($_POST['action'])) {
        $v_id = new MongoDB\BSON\ObjectId($_POST['vendor_id']);
        $new_status = ($_POST['action'] === 'approve') ? 'active' : 'suspended'; // Or 'rejected' if you prefer
        
        $vendorsCollection->updateOne(
            ['_id' => $v_id],
            ['$set' => ['status' => $new_status, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
        );
        
        $msg = ($new_status === 'active') ? "تم تفعيل المتجر بنجاح!" : "تم رفض/تجميد المتجر.";
        $msg_type = "success";
    }

    // 3. جلب الإحصائيات
    $stats = [];
    $stats['users'] = $usersCollection->countDocuments(['role' => 'customer']);
    $stats['vendors'] = $vendorsCollection->countDocuments(['status' => 'active']);
    $stats['pending'] = $vendorsCollection->countDocuments(['status' => 'pending']);
    $stats['orders'] = $ordersCollection->countDocuments([]);

    // 4. جلب قائمة التجار المعلقين (Pending)
    // نحتاج لبيانات المستخدم (الاسم والهاتف) من مجموعة users
    $pipeline = [
        ['$match' => ['status' => 'pending']],
        ['$lookup' => [
            'from' => 'users',
            'localField' => 'user_id',
            'foreignField' => '_id',
            'as' => 'owner'
        ]],
        ['$unwind' => '$owner'],
        ['$project' => [
            'id' => '$_id',
            'store_name' => 1,
            'store_type' => 1,
            'created_at' => 1,
            'documents' => 1, // جلب حقل الوثائق
            'first_name' => '$owner.first_name',
            'last_name' => '$owner.last_name',
            'phone' => '$owner.phone'
        ]]
    ];
    
    $pending_vendors = $vendorsCollection->aggregate($pipeline)->toArray();

} catch(Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة | الصقر مول</title>
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
    <div class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-2xl font-bold animate-bounce bg-green-600">
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
                    <li><a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg"><i class="fas fa-th-large"></i> الرئيسية</a></li>
                    <li><a href="vendors.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-store"></i> إدارة المتاجر</a></li>
                    <li><a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-tags"></i> الأقسام</a></li>
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box-open"></i> المنتجات</a></li>
                    <li><a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-chart-line"></i> التقارير</a></li>
                    <li><a href="profile.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-user-cog"></i> الملف الشخصي</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/20 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> لوحة الإدارة</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <div class="mb-8 flex justify-between items-end">
                <div>
                    <h1 class="text-3xl font-bold mb-2">نظرة عامة</h1>
                    <p class="text-slate-400">مرحباً بك، إليك ملخص لما يحدث في الصقر مول اليوم.</p>
                </div>
                <div class="text-sm bg-slate-800 px-4 py-2 rounded-full border border-slate-700 text-slate-300">
                    <i class="far fa-calendar-alt ml-2"></i> <?php echo date("Y-m-d"); ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl p-6 shadow-lg relative overflow-hidden group">
                    <div class="relative z-10">
                        <div class="text-white/80 text-sm font-bold mb-1">طلبات المتاجر</div>
                        <div class="text-4xl font-black text-white"><?php echo $stats['pending']; ?></div>
                    </div>
                    <i class="fas fa-store absolute -left-4 -bottom-4 text-8xl text-black/10 group-hover:scale-110 transition-transform"></i>
                </div>

                <div class="bg-brand-sidebar border border-slate-700 rounded-2xl p-6 shadow-lg relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="text-slate-400 text-sm font-bold mb-1">إجمالي الطلبات</div>
                        <div class="text-4xl font-black text-white"><?php echo $stats['orders']; ?></div>
                    </div>
                    <i class="fas fa-shopping-bag absolute -left-4 -bottom-4 text-8xl text-brand-gold/5"></i>
                </div>

                <div class="bg-brand-sidebar border border-slate-700 rounded-2xl p-6 shadow-lg relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="text-slate-400 text-sm font-bold mb-1">متاجر نشطة</div>
                        <div class="text-4xl font-black text-white"><?php echo $stats['vendors']; ?></div>
                    </div>
                    <i class="fas fa-check-circle absolute -left-4 -bottom-4 text-8xl text-green-500/5"></i>
                </div>

                <div class="bg-brand-sidebar border border-slate-700 rounded-2xl p-6 shadow-lg relative overflow-hidden">
                    <div class="relative z-10">
                        <div class="text-slate-400 text-sm font-bold mb-1">عدد الزبائن</div>
                        <div class="text-4xl font-black text-white"><?php echo $stats['users']; ?></div>
                    </div>
                    <i class="fas fa-users absolute -left-4 -bottom-4 text-8xl text-blue-500/5"></i>
                </div>
            </div>

            <!-- Pending Vendors Table -->
            <div class="mb-6">
                <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <span class="w-2 h-8 bg-brand-gold rounded-full block"></span>
                    طلبات انضمام المتاجر (Pending)
                </h2>
            </div>

            <?php if(count($pending_vendors) > 0): ?>
            <div class="overflow-x-auto rounded-2xl glass-table">
                <table class="w-full text-right">
                    <thead class="bg-slate-800/50 text-slate-300 border-b border-slate-700">
                        <tr>
                            <th class="p-4">اسم المتجر</th>
                            <th class="p-4">المالك</th>
                            <th class="p-4">نوع التجارة</th>
                            <th class="p-4">تاريخ الطلب</th>
                            <th class="p-4 text-center">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 text-sm">
                        <?php foreach($pending_vendors as $vendor): ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="p-4 font-bold text-white">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-brand-gold">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <?php echo htmlspecialchars($vendor['store_name']); ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="text-white"><?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></div>
                                <div class="text-slate-500 text-xs"><?php echo htmlspecialchars($vendor['phone']); ?></div>
                            </td>
                            <td class="p-4">
                                <span class="bg-slate-700 px-3 py-1 rounded-full text-xs text-slate-300">
                                    <?php echo htmlspecialchars($vendor['store_type']); ?>
                                </span>
                            </td>
                            <td class="p-4 text-slate-400">
                            <?php 
                                $date = isset($vendor['created_at']) ? $vendor['created_at']->toDateTime()->format('Y/m/d') : 'غير متوفر';
                                echo $date;
                            ?>
                            </td>
                            <td class="p-4 flex justify-center gap-2">
                                <?php if(isset($vendor['documents']['profile_pdf'])): ?>
                                    <a href="../<?php echo htmlspecialchars($vendor['documents']['profile_pdf']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-500 hover:bg-blue-500 hover:text-white transition-all flex items-center justify-center" title="استعراض الملف">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('هل أنت متأكد من تفعيل هذا المتجر؟');">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-green-500/20 text-green-500 hover:bg-green-500 hover:text-white transition-all flex items-center justify-center">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('هل أنت متأكد من رفض هذا الطلب؟');">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-red-500/20 text-red-500 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="bg-slate-800/50 border border-slate-700 rounded-2xl p-10 text-center">
                    <div class="w-20 h-20 bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-green-500 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">كل شيء هادئ هنا!</h3>
                    <p class="text-slate-400">لا توجد طلبات متاجر جديدة معلقة في الوقت الحالي.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>

</body>
</html>