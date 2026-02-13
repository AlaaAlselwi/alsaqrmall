<?php
session_start();
require_once '../includes/db.php';

// حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

try {
    $db = Database::connect();
    $ordersCollection = $db->orders;

    // 1. بيانات الرسم البياني (المبيعات آخر 30 يوم)
    // نحتاج تجميع المبيعات حسب التاريخ
    $thirtyDaysAgo = new MongoDB\BSON\UTCDateTime((time() - 30 * 24 * 60 * 60) * 1000);
    
    $salesPipeline = [
        [
            '$match' => [
                'status' => ['$ne' => 'cancelled'],
                'created_at' => ['$gte' => $thirtyDaysAgo]
            ]
        ],
        [
            '$group' => [
                '_id' => [
                    '$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']
                ],
                'total' => ['$sum' => '$total_amount']
            ]
        ],
        ['$sort' => ['_id' => 1]]
    ];
    
    $sales_data = $ordersCollection->aggregate($salesPipeline)->toArray();

    // تجهيز البيانات للجافاسكريبت
    $dates = [];
    $totals = [];
    foreach ($sales_data as $day) {
        $dates[] = date('m/d', strtotime($day['_id']));
        $totals[] = $day['total'];
    }

    // 2. أكثر المتاجر مبيعاً (Top Vendors)
    // يعتمد على تحليل العناصر داخل الطلبات
    $topVendorsPipeline = [
        ['$match' => ['status' => ['$ne' => 'cancelled']]],
        ['$unwind' => '$items'],
        [
            '$group' => [
                '_id' => '$items.vendor_id',
                'order_ids' => ['$addToSet' => '$_id'], // لعد الطلبات الفريدة
                'revenue' => ['$sum' => ['$multiply' => ['$items.price', '$items.quantity']]]
            ]
        ],
        [
            '$project' => [
                'orders_count' => ['$size' => '$order_ids'],
                'revenue' => 1
            ]
        ],
        ['$sort' => ['revenue' => -1]],
        ['$limit' => 5],
        [
            '$lookup' => [
                'from' => 'vendors',
                'localField' => '_id',
                'foreignField' => '_id',
                'as' => 'vendor_info'
            ]
        ],
        ['$unwind' => '$vendor_info'],
        [
            '$project' => [
                'store_name' => '$vendor_info.store_name',
                'logo' => '$vendor_info.logo',
                'orders_count' => 1,
                'revenue' => 1
            ]
        ]
    ];
    
    $top_vendors = $ordersCollection->aggregate($topVendorsPipeline)->toArray();

    // 3. أكثر المنتجات مبيعاً (Top Products)
    $topProductsPipeline = [
        ['$match' => ['status' => ['$ne' => 'cancelled']]],
        ['$unwind' => '$items'],
        [
            '$group' => [
                '_id' => '$items.product_id',
                'sold_qty' => ['$sum' => '$items.quantity']
            ]
        ],
        ['$sort' => ['sold_qty' => -1]],
        ['$limit' => 5],
        [
            '$lookup' => [
                'from' => 'products',
                'localField' => '_id',
                'foreignField' => '_id',
                'as' => 'product_info'
            ]
        ],
        ['$unwind' => '$product_info'],
        // نحتاج اسم المتجر أيضاً
        [
            '$lookup' => [
                'from' => 'vendors',
                'localField' => 'product_info.vendor_id',
                'foreignField' => '_id',
                'as' => 'vendor_info'
            ]
        ],
        ['$unwind' => '$vendor_info'],
        [
            '$project' => [
                'name' => '$product_info.name',
                'image' => '$product_info.image',
                'store_name' => '$vendor_info.store_name',
                'sold_qty' => 1
            ]
        ]
    ];

    $top_products = $ordersCollection->aggregate($topProductsPipeline)->toArray();

    // 4. إحصائيات عامة
    // إجمالي الإيرادات ومتوسط الطلب
    $generalStatsPipeline = [
        ['$match' => ['status' => ['$ne' => 'cancelled']]],
        [
            '$group' => [
                '_id' => null,
                'total_revenue' => ['$sum' => '$total_amount'],
                'avg_order' => ['$avg' => '$total_amount']
            ]
        ]
    ];
    
    $statsResult = $ordersCollection->aggregate($generalStatsPipeline)->toArray();
    $total_revenue = !empty($statsResult) ? $statsResult[0]['total_revenue'] : 0;
    $avg_order = !empty($statsResult) ? $statsResult[0]['avg_order'] : 0;

} catch(Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والتحليلات | الصقر مول</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- مكتبة Chart.js للرسوم البيانية -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        body::-webkit-scrollbar { width: 8px; }
        body::-webkit-scrollbar-track { background: #0f172a; }
        body::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
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
                    <li><a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-tags"></i> الأقسام</a></li>
                    <li><a href="users.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-users"></i> المستخدمين</a></li>
                    <li><a href="products.php" class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:bg-slate-800 hover:text-white rounded-xl transition-colors"><i class="fas fa-box-open"></i> المنتجات</a></li>
                    <li><a href="reports.php" class="flex items-center gap-3 px-4 py-3 bg-brand-gold text-brand-dark rounded-xl font-bold shadow-lg shadow-yellow-500/20"><i class="fas fa-chart-line"></i> التقارير</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-slate-700">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-red-900/20 rounded-xl transition-colors"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 md:mr-64 p-4 md:p-8">
            
            <header class="flex justify-between items-center mb-8 md:hidden">
                <div class="text-xl font-bold text-brand-gold"><i class="fas fa-eagle"></i> التقارير</div>
                <button class="text-white text-2xl"><i class="fas fa-bars"></i></button>
            </header>

            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2">التقارير والتحليلات</h1>
                <p class="text-slate-400">رؤية شاملة لأداء المول والمبيعات.</p>
            </div>

            <!-- ملخص سريع -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="glass-card rounded-2xl p-6 flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-sm mb-1">إجمالي الإيرادات (الكل)</p>
                        <h2 class="text-3xl font-bold text-green-400"><?php echo number_format($total_revenue); ?> ر.ي</h2>
                    </div>
                    <div class="w-12 h-12 bg-green-500/10 rounded-full flex items-center justify-center text-green-500 text-2xl">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="glass-card rounded-2xl p-6 flex items-center justify-between">
                    <div>
                        <p class="text-slate-400 text-sm mb-1">متوسط قيمة الطلب</p>
                        <h2 class="text-3xl font-bold text-brand-gold"><?php echo number_format($avg_order); ?> ر.ي</h2>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/10 rounded-full flex items-center justify-center text-brand-gold text-2xl">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>

            <!-- الرسم البياني -->
            <div class="glass-card rounded-2xl p-6 mb-8">
                <h3 class="font-bold text-lg mb-6 border-b border-slate-700 pb-2">تحليل المبيعات (آخر 30 يوم)</h3>
                <div class="h-80 w-full">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- أفضل المتاجر -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-6 border-b border-slate-700 pb-2 flex justify-between items-center">
                        <span>🏆 أفضل المتاجر أداءً</span>
                        <span class="text-xs bg-slate-700 px-2 py-1 rounded">بالإيرادات</span>
                    </h3>
                    <div class="space-y-4">
                        <?php foreach($top_vendors as $index => $vendor): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-800/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-slate-500 text-lg">#<?php echo $index + 1; ?></span>
                                <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center overflow-hidden border border-slate-600">
                                    <?php if(isset($vendor['logo']) && $vendor['logo']): ?>
                                        <img src="../<?php echo $vendor['logo']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-store text-slate-400"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold"><?php echo htmlspecialchars($vendor['store_name']); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo $vendor['orders_count']; ?> طلبات</div>
                                </div>
                            </div>
                            <div class="font-bold text-brand-gold"><?php echo number_format($vendor['revenue']); ?> ر.ي</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- أكثر المنتجات مبيعاً -->
                <div class="glass-card rounded-2xl p-6">
                    <h3 class="font-bold text-lg mb-6 border-b border-slate-700 pb-2 flex justify-between items-center">
                        <span>🔥 المنتجات الأكثر طلباً</span>
                        <span class="text-xs bg-slate-700 px-2 py-1 rounded">بالكمية</span>
                    </h3>
                    <div class="space-y-4">
                        <?php foreach($top_products as $index => $prod): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-800/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-slate-700 flex items-center justify-center overflow-hidden border border-slate-600">
                                    <?php if(isset($prod['image']) && $prod['image']): ?>
                                        <img src="../<?php echo $prod['image']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-box text-slate-400"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-sm"><?php echo htmlspecialchars($prod['name']); ?></div>
                                    <div class="text-xs text-blue-400"><?php echo htmlspecialchars($prod['store_name']); ?></div>
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="font-bold text-white text-lg"><?php echo $prod['sold_qty']; ?></div>
                                <div class="text-[10px] text-slate-500 uppercase">قطعة</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- إعداد الرسم البياني -->
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // تدرج لوني للخلفية
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(251, 191, 36, 0.5)'); // brand-gold
        gradient.addColorStop(1, 'rgba(251, 191, 36, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'المبيعات اليومية (ر.ي)',
                    data: <?php echo json_encode($totals); ?>,
                    borderColor: '#fbbf24',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#fbbf24',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#cbd5e1', font: { family: 'Tajawal' } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    </script>
</body>
</html>