<?php
// إعداد عنوان الصفحة قبل استدعاء الهيدر
$page_title = 'تفاصيل المنتج | الصقر مول';
require_once 'includes/header.php';

// التحقق من وجود المعرف
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit();
}

try {
    $db = Database::connect();
    $productsCollection = $db->products;
    
    $product_id = new MongoDB\BSON\ObjectId($_GET['id']);

    // جلب تفاصيل المنتج مع بيانات المتجر والقسم
    $pipeline = [
        ['$match' => ['_id' => $product_id]],
        // ربط مع المتجر
        ['$lookup' => [
            'from' => 'vendors',
            'localField' => 'vendor_id',
            'foreignField' => '_id',
            'as' => 'vendor'
        ]],
        ['$unwind' => '$vendor'],
        // ربط مع القسم
        ['$lookup' => [
            'from' => 'categories',
            'localField' => 'category_id',
            'foreignField' => '_id',
            'as' => 'category'
        ]],
        // ربط مع المستخدم (للحصول على رقم الهاتف)
        ['$lookup' => [
            'from' => 'users',
            'localField' => 'vendor.user_id',
            'foreignField' => '_id',
            'as' => 'vendor_user'
        ]],
        ['$unwind' => '$vendor_user'],
        
        ['$project' => [
            'name' => 1,
            'description' => 1,
            'price' => 1,
            'old_price' => 1,
            'image' => 1,
            'stock' => 1,
            'category_id' => 1,
            'views' => 1,
            'store_name' => '$vendor.store_name',
            'vendor_logo' => '$vendor.logo',
            'vendor_status' => '$vendor.status',
            'vendor_phone' => '$vendor_user.phone',
            'category_name' => ['$arrayElemAt' => ['$category.name', 0]]
        ]]
    ];

    $product = $productsCollection->aggregate($pipeline)->toArray();

    if (empty($product)) {
        echo "<div class='container mx-auto py-20 text-center text-3xl font-bold'>المنتج غير موجود.</div>";
        require_once 'includes/footer.php';
        exit();
    }

    $product = $product[0];

    // التحقق من حالة التاجر
    if (isset($product['vendor_status']) && $product['vendor_status'] !== 'active') {
        echo "<div class='container mx-auto py-20 text-center text-3xl font-bold text-red-500'>عذراً، هذا المنتج غير متاح حالياً.</div>";
        require_once 'includes/footer.php';
        exit();
    }

    // تحديث عدد المشاهدات
    $productsCollection->updateOne(
        ['_id' => $product_id],
        ['$inc' => ['views' => 1]]
    );

    // جلب منتجات مشابهة (نفس القسم، ليس المنتج الحالي)
    $related_products = [];
    if (isset($product['category_id'])) {
        $pipelineRelated = [
            ['$match' => [
                'category_id' => $product['category_id'],
                '_id' => ['$ne' => $product_id],
                'stock' => ['$gt' => 0]
            ]],
            ['$limit' => 4],
            ['$project' => [
                'name' => 1,
                'price' => 1,
                'image' => 1,
                'store_name' => 1 // نحتاج نعمل lookup لو نبي اسم المتجر هنا، بس للتبسيط ممكن نتجاهله أو نعمله
            ]]
        ];
        // لجلب اسم المتجر للمنتجات المشابهة، نحتاج lookup بسيط
        // سنكتفي بالبيانات الأساسية للسرعة، أو نضيف lookup
        $related_products = $productsCollection->aggregate([
            ['$match' => [
                'category_id' => $product['category_id'],
                '_id' => ['$ne' => $product_id],
                'stock' => ['$gt' => 0]
            ]],
            ['$limit' => 4],
             ['$lookup' => [
                'from' => 'vendors',
                'localField' => 'vendor_id',
                'foreignField' => '_id',
                'as' => 'vendor'
            ]],
            ['$unwind' => '$vendor'],
            ['$project' => [
                'name' => 1,
                'price' => 1,
                'image' => 1,
                'store_name' => '$vendor.store_name'
            ]]
        ])->toArray();
    }

} catch (Exception $e) {
    echo "حدث خطأ: " . $e->getMessage();
    exit();
}
?>

    <div class="container mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="text-sm text-slate-400 mb-6 flex items-center gap-2">
            <a href="index.php" class="hover:text-brand-gold">الرئيسية</a>
            <i class="fas fa-chevron-left text-xs"></i>
            <span class="text-brand-gold"><?php echo htmlspecialchars($product['category_name'] ?? 'عام'); ?></span>
            <i class="fas fa-chevron-left text-xs"></i>
            <span class="text-white truncate"><?php echo htmlspecialchars($product['name']); ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Product Image -->
            <div class="space-y-4" data-aos="fade-left">
                <div class="glass-nav rounded-3xl p-2 aspect-square relative group overflow-hidden border border-slate-700">
                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" class="w-full h-full object-cover rounded-2xl group-hover:scale-105 transition-transform duration-500" id="mainImage">
                    <span class="absolute top-4 right-4 bg-brand-gold text-brand-dark font-bold px-3 py-1 rounded-full text-sm">
                        <?php echo $product['stock'] > 0 ? 'متوفر' : 'نفذت الكمية'; ?>
                    </span>
                </div>
            </div>

            <!-- Product Info -->
            <div class="flex flex-col justify-center" data-aos="fade-right">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-slate-700 overflow-hidden border border-slate-600">
                        <?php if(isset($product['vendor_logo']) && $product['vendor_logo']): ?>
                            <img src="../<?php echo $product['vendor_logo']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-500"><i class="fas fa-store"></i></div>
                        <?php endif; ?>
                    </div>
                    <a href="#" class="text-sm text-slate-300 hover:text-brand-gold transition underline decoration-slate-600">
                        متجر: <?php echo htmlspecialchars($product['store_name']); ?>
                    </a>
                </div>

                <h1 class="text-3xl md:text-5xl font-black mb-4 leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="flex items-end gap-4 mb-6">
                    <span class="text-4xl font-bold text-brand-gold"><?php echo number_format($product['price']); ?> <span class="text-lg text-slate-300">ر.ي</span></span>
                    <?php if(!empty($product['old_price'])): ?>
                    <span class="text-xl text-slate-500 line-through mb-1"><?php echo number_format($product['old_price']); ?> ر.ي</span>
                    <?php endif; ?>
                </div>

                <p class="text-slate-400 leading-relaxed mb-8 border-r-4 border-slate-700 pr-4">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>

                <div class="space-y-4">
                    <form action="cart.php" method="POST" class="flex gap-4">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo $product['_id']; ?>">
                        
                        <div class="w-24">
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" class="w-full h-full bg-slate-800 border border-slate-600 rounded-xl text-center text-white focus:border-brand-gold outline-none font-bold">
                        </div>
                        
                        <button type="submit" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?> class="flex-1 bg-brand-gold hover:bg-yellow-500 text-brand-dark font-black py-4 rounded-xl shadow-lg shadow-yellow-500/20 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-cart-plus text-xl"></i> 
                            <?php echo $product['stock'] > 0 ? 'أضف للسلة' : 'غير متوفر حالياً'; ?>
                        </button>
                    </form>

                    <?php 
                        $wa_phone = preg_replace('/^0/', '967', $product['vendor_phone']);
                        $wa_msg = urlencode("مرحباً، أريد الاستفسار عن المنتج: " . $product['name']);
                    ?>
                    <a href="https://wa.me/<?php echo $wa_phone; ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl text-center transition-colors flex items-center justify-center gap-2">
                        <i class="fab fa-whatsapp text-2xl"></i> اطلب عبر واتساب
                    </a>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <div class="mt-20">
            <h2 class="text-2xl font-bold mb-8 border-r-4 border-brand-gold pr-4">قد يعجبك أيضاً</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach($related_products as $rel): ?>
                <a href="product_details.php?id=<?php echo $rel['_id']; ?>" class="bg-slate-800 rounded-2xl overflow-hidden group hover:border-brand-gold/50 border border-slate-700 transition-all block">
                    <div class="aspect-[4/3] overflow-hidden bg-slate-700 relative">
                        <img src="../<?php echo htmlspecialchars($rel['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-lg mb-1 truncate"><?php echo htmlspecialchars($rel['name']); ?></h3>
                        <div class="text-xs text-slate-400 mb-2"><?php echo htmlspecialchars($rel['store_name']); ?></div>
                        <div class="flex justify-between items-center">
                            <span class="text-brand-gold font-bold"><?php echo number_format($rel['price']); ?> ر.ي</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>