<?php
$page_title = 'الصقر مول | Alsaqrmall - تجربة تسوق استثنائية';
require_once 'includes/header.php'; // Header now handles session and DB connection

// متغيرات لتخزين البيانات
$latest_products = [];
$featured_products = [];

try {
    $db = Database::connect();
    $productsCollection = $db->products;

    // إعداد الـ Pipeline لجلب المنتجات مع بيانات المتجر
    $lookupStage = [
        '$lookup' => [
            'from' => 'vendors',
            'localField' => 'vendor_id',
            'foreignField' => '_id',
            'as' => 'vendor'
        ]
    ];
    // use unwind with preserveNullAndEmptyArrays to avoid losing products if vendor is missing (though shouldn't happen)
    $unwindStage = ['$unwind' => ['path' => '$vendor', 'preserveNullAndEmptyArrays' => true]]; 
    
    $projectStage = [
        '$project' => [
            'name' => 1,
            'description' => 1,
            'price' => 1,
            'old_price' => 1,
            'image' => 1,
            'stock' => 1,
            'is_featured' => 1,
            'store_name' => ['$ifNull' => ['$vendor.store_name', 'متجر غير معروف']],
            'id' => '$_id'
        ]
    ];

    // 1. جلب أحدث 8 منتجات
    $pipelineLatest = [
        ['$match' => ['stock' => ['$gt' => 0]]], // المنتجات المتوفرة فقط
        ['$sort' => ['created_at' => -1]],
        ['$limit' => 8],
        $lookupStage,
        ['$unwind' => '$vendor'], // Must exist
        ['$match' => ['vendor.status' => 'active']], // Only active vendors
        $projectStage
    ];
    $latest_products = $productsCollection->aggregate($pipelineLatest)->toArray();

    // 2. جلب المنتجات المميزة (Featured)
    $pipelineFeatured = [
        ['$match' => ['is_featured' => true, 'stock' => ['$gt' => 0]]],
        ['$limit' => 4],
        ['$limit' => 4],
        $lookupStage,
        ['$unwind' => '$vendor'],
        ['$match' => ['vendor.status' => 'active']],
        $projectStage
    ];
    $featured_products = $productsCollection->aggregate($pipelineFeatured)->toArray();

    // 3. جلب التصنيفات للقائمة الجانبية
    $categories = $db->categories->find([], ['sort' => ['name' => 1]])->toArray();

} catch (Exception $e) {
    echo "<div class='container mx-auto p-4 text-red-500'>حدث خطأ في جلب البيانات: " . $e->getMessage() . "</div>";
}
?>



    <!-- Wrapper الرئيسي -->
    <div class="container mx-auto px-4 py-6">
        
        <div class="flex flex-col lg:flex-row gap-6">
            
            <!-- 1. القائمة الجانبية (Categories Sidebar) - تظهر بجانب الهيرو في الديسكتوب -->
            <aside class="hidden lg:block w-1/4">
                <div class="bg-slate-800 rounded-2xl overflow-hidden border border-slate-700 shadow-2xl h-full">
                    <div class="bg-brand-gold p-4 font-black text-brand-dark flex items-center justify-between">
                        <span class="flex items-center gap-2"><i class="fas fa-bars"></i> جميع الأقسام</span>
                        <i class="fas fa-chevron-down text-sm opacity-50"></i>
                    </div>
                    <ul class="divide-y divide-slate-700">
                        <li>
                            <a href="index.php" class="block px-5 py-3 hover:bg-slate-700 hover:text-brand-gold transition-colors text-sm font-bold border-l-4 border-transparent hover:border-brand-gold flex items-center gap-3">
                                <i class="fas fa-th-large w-5 text-center text-slate-400"></i> الكل
                            </a>
                        </li>
                        <?php foreach($categories as $index => $cat): ?>
                        <li>
                            <a href="index.php?category=<?php echo $cat['_id']; ?>" class="block px-5 py-3 hover:bg-slate-700 hover:text-brand-gold transition-colors text-sm text-slate-300 border-l-4 border-transparent hover:border-brand-gold flex items-center gap-3 group">
                                <!-- أيقونات ديناميكية بناءً على الإندكس للتنويع -->
                                <?php 
                                    $icons = ['fa-mobile-alt', 'fa-tshirt', 'fa-home', 'fa-desktop', 'fa-gamepad', 'fa-baby', 'fa-pump-medical', 'fa-book'];
                                    $icon = $icons[$index % count($icons)];
                                ?>
                                <i class="fas <?php echo $icon; ?> w-5 text-center text-slate-500 group-hover:text-brand-gold transition-colors"></i>
                                <?php echo htmlspecialchars($cat['name']); ?>
                                <i class="fas fa-chevron-left mr-auto text-xs opacity-0 group-hover:opacity-100 transition-opacity"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <!-- رابط وهمي للمزيد -->
                        <li>
                            <a href="#" class="block px-5 py-3 text-brand-accent hover:text-white transition-colors text-sm font-bold flex items-center gap-3">
                                <i class="fas fa-plus-circle w-5 text-center"></i> المزيد من الفئات...
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- 2. الهيرو بانر (Hero Banner) - يأخذ المساحة المتبقية -->
            <main class="w-full lg:w-3/4">
                <div class="relative h-[400px] md:h-[500px] rounded-2xl overflow-hidden shadow-2xl group">
                    <!-- خلفية الصورة -->
                    <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80')] bg-cover bg-center transition-transform duration-1000 group-hover:scale-105"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-brand-dark/90 via-brand-dark/40 to-transparent"></div>
                    
                    <div class="absolute inset-0 flex items-center">
                        <div class="px-8 md:px-12 max-w-2xl" data-aos="fade-right">
                            <span class="inline-block py-1 px-3 rounded-md bg-brand-gold text-brand-dark text-xs font-bold mb-4">
                                عروض الموسم 🏷️
                            </span>
                            <h1 class="text-4xl md:text-6xl font-black mb-4 leading-tight">
                                أحدث صيحات <br>
                                <span class="text-brand-gold">الموضة والتقنية</span>
                            </h1>
                            <p class="text-slate-200 text-lg mb-8 font-light">
                                تشكيلة واسعة من المنتجات العالمية بين يديك. توصيل سريع وضمان حقيقي.
                            </p>
                            <div class="flex gap-4">
                                <a href="#products" class="bg-brand-gold text-brand-dark font-bold py-3 px-8 rounded-lg hover:bg-white transition-colors shadow-lg">
                                    تسوق الآن
                                </a>
                                <a href="../register.php" class="bg-white/10 backdrop-blur-sm border border-white/20 text-white font-bold py-3 px-8 rounded-lg hover:bg-white/20 transition-colors">
                                    انضم كتاجر
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- نقاط السلايدر (ديكور) -->
                    <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 flex gap-2">
                        <span class="w-8 h-2 bg-brand-gold rounded-full cursor-pointer"></span>
                        <span class="w-2 h-2 bg-white/50 rounded-full cursor-pointer hover:bg-white transition-colors"></span>
                        <span class="w-2 h-2 bg-white/50 rounded-full cursor-pointer hover:bg-white transition-colors"></span>
                    </div>
                </div>
            </main>

        </div>
    </div>

    <!-- 3. شريط المميزات (Features Strip) -->
    <div class="container mx-auto px-4 mb-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-slate-800 rounded-2xl p-6 border border-slate-700 shadow-lg mt-6">
            <div class="flex items-center gap-4 border-l border-slate-700 pl-4 justify-center md:justify-start">
                <i class="fas fa-truck-fast text-3xl text-brand-gold"></i>
                <div>
                    <h4 class="font-bold text-sm">توصيل سريع</h4>
                    <p class="text-xs text-slate-400">لجميع المحافظات</p>
                </div>
            </div>
            <div class="flex items-center gap-4 border-l border-slate-700 pl-4 justify-center md:justify-start">
                <i class="fas fa-shield-alt text-3xl text-brand-gold"></i>
                <div>
                    <h4 class="font-bold text-sm">دفع آمن</h4>
                    <p class="text-xs text-slate-400">عند الاستلام أو محفظة</p>
                </div>
            </div>
            <div class="flex items-center gap-4 border-l border-slate-700 pl-4 justify-center md:justify-start">
                <i class="fas fa-headset text-3xl text-brand-gold"></i>
                <div>
                    <h4 class="font-bold text-sm">دعم فني</h4>
                    <p class="text-xs text-slate-400">متواجدون 24/7</p>
                </div>
            </div>
            <div class="flex items-center gap-4 justify-center md:justify-start">
                <i class="fas fa-gifts text-3xl text-brand-gold"></i>
                <div>
                    <h4 class="font-bold text-sm">عروض حصرية</h4>
                    <p class="text-xs text-slate-400">خصومات للأعضاء</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. محتوى الصفحة الرئيسي (المنتجات) -->
    <div class="container mx-auto px-4 py-8" id="main-content">
        
        <!-- قائمة التصنيفات للموبايل (Horizontal Scroll) -->
        <div class="lg:hidden mb-8">
            <h3 class="font-bold mb-3 flex items-center gap-2">
                <i class="fas fa-tags text-brand-gold"></i> الأقسام
            </h3>
            <div class="flex gap-3 overflow-x-auto no-scrollbar pb-2">
                <a href="index.php" class="whitespace-nowrap bg-brand-gold text-brand-dark px-4 py-2 rounded-lg font-bold text-sm shadow-md">
                    الكل
                </a>
                <?php foreach($categories as $cat): ?>
                <a href="index.php?category=<?php echo $cat['_id']; ?>" class="whitespace-nowrap bg-slate-800 border border-slate-700 text-slate-300 px-4 py-2 rounded-lg text-sm hover:border-brand-gold hover:text-white transition-colors shadow-sm">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- المنتجات المميزة والجديدة -->
                <?php if(count($featured_products) > 0): ?>
                <section>
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold border-r-4 border-brand-gold pr-3">عروض <span class="text-gradient">مميزة</span> 🔥</h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($featured_products as $prod): ?>
                        <a href="product_details.php?id=<?php echo $prod['id']; ?>" class="bg-slate-800 rounded-2xl p-4 product-card border border-slate-700 relative group overflow-hidden block" data-aos="fade-up">
                            <span class="absolute top-4 right-4 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded z-10">مميز</span>
                            
                            <div class="h-48 rounded-xl bg-slate-700 mb-4 overflow-hidden relative">
                                <img src="../<?php echo htmlspecialchars($prod['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                                <!-- زر إضافة سريع -->
                                <button class="absolute bottom-2 right-2 bg-brand-gold text-brand-dark w-8 h-8 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <div class="text-slate-400 text-xs mb-1 flex items-center gap-1">
                                <i class="fas fa-store"></i> <?php echo htmlspecialchars($prod['store_name']); ?>
                            </div>
                            <h3 class="text-white font-bold text-lg mb-2 truncate"><?php echo htmlspecialchars($prod['name']); ?></h3>
                            <div class="flex justify-between items-end">
                                <div>
                                    <?php if(isset($prod['old_price']) && $prod['old_price']): ?>
                                        <span class="block text-slate-500 line-through text-xs"><?php echo number_format($prod['old_price']); ?></span>
                                    <?php endif; ?>
                                    <span class="text-brand-gold font-bold text-xl"><?php echo number_format($prod['price']); ?> <span class="text-xs font-normal text-slate-400">ر.ي</span></span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- أحدث المنتجات -->
                <section id="products">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold border-r-4 border-brand-accent pr-3">وصل <span class="text-white">حديثاً</span></h2>
                        <div class="flex gap-2">
                             <!-- يمكن إضافة أدوات فرز هنا -->
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if(count($latest_products) > 0): ?>
                            <?php foreach($latest_products as $prod): ?>
                            <a href="product_details.php?id=<?php echo $prod['id']; ?>" class="bg-slate-800 rounded-2xl p-4 product-card border border-slate-700 relative group overflow-hidden block" data-aos="fade-up">
                                <div class="h-48 rounded-xl bg-slate-700 mb-4 overflow-hidden relative">
                                    <img src="../<?php echo htmlspecialchars($prod['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                                </div>
                                <div class="text-slate-400 text-xs mb-1 flex items-center gap-1">
                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($prod['store_name']); ?>
                                </div>
                                <h3 class="text-white font-bold text-lg mb-2 truncate"><?php echo htmlspecialchars($prod['name']); ?></h3>
                                <div class="flex justify-between items-end">
                                    <div>
                                        <span class="text-brand-gold font-bold text-xl"><?php echo number_format($prod['price']); ?> <span class="text-xs font-normal text-slate-400">ر.ي</span></span>
                                    </div>
                                    <div class="bg-slate-700 text-white w-9 h-9 rounded-lg flex items-center justify-center group-hover:bg-brand-gold group-hover:text-brand-dark transition-colors">
                                        <i class="fas fa-shopping-cart text-sm"></i>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-full text-center py-20 bg-slate-800/30 rounded-2xl border border-slate-700/50">
                                <i class="fas fa-search text-6xl text-slate-600 mb-4"></i>
                                <p class="text-slate-400">لا توجد منتجات مطابقة حالياً.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-12 text-center">
                         <button class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white px-8 py-3 rounded-full transition-all">
                            عرض المزيد من المنتجات <i class="fas fa-chevron-down mr-2"></i>
                         </button>
                    </div>
                </section>

    </div>
<?php require_once 'includes/footer.php'; ?>

    <!-- 6. قسم دعوة للمتاجر (Call to Action) -->
    <section class="py-20 relative overflow-hidden bg-brand-gold/5">
        <div class="container mx-auto px-4 relative z-10 text-center">
            <h2 class="text-4xl md:text-5xl font-black mb-6 text-white" data-aos="zoom-in">هل تمتلك متجراً؟</h2>
            <p class="text-slate-300 text-xl mb-8 max-w-2xl mx-auto">انضم إلى "الصقر مول" وتوسع في مبيعاتك لتصل إلى آلاف العملاء في اليمن.</p>
            <a href="../register.php" class="inline-block bg-white text-brand-dark font-bold py-4 px-12 rounded-full hover:bg-brand-gold hover:shadow-2xl transition-all transform hover:-translate-y-1 text-lg">
                ابدأ البيع الآن <i class="fas fa-rocket mr-2"></i>
            </a>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>