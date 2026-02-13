<?php
// إعدادات MongoDB
require_once 'includes/db.php';

try {
    // 1. الاتصال بقاعدة البيانات
    $db = Database::connect();
    
    echo "<div style='font-family:tahoma; direction:rtl; text-align:right; padding:20px;'>";
    echo "✅ تم الاتصال بقاعدة البيانات (MongoDB) بنجاح.<br>";

    // ==========================================
    // إنشاء الفهارس (Indexes)
    // ==========================================
    // في MongoDB لا نحتاج لإنشاء الجداول (Collections) مسبقاً، فهي تنشأ تلقائياً عند إضافة بيانات.
    // لكن من الممارسات الجيدة إنشاء الفهارس لضمان الأداء والقيود (مثل عدم تكرار رقم الهاتف).

    // 1. مجموعة المستخدمين (users)
    $usersCollection = $db->users;
    $usersCollection->createIndex(['phone' => 1], ['unique' => true]);
    $usersCollection->createIndex(['email' => 1], ['unique' => true, 'sparse' => true]); // في حال أضفنا إيميل لاحقاً
    echo "✅ تم إعداد مجموعة المستخدمين (users) وإنشاء الفهارس.<br>";

    // 2. مجموعة المتاجر (vendors)
    $vendorsCollection = $db->vendors;
    $vendorsCollection->createIndex(['user_id' => 1], ['unique' => true]); // كل مستخدم له متجر واحد
    $vendorsCollection->createIndex(['store_name' => 1]);
    echo "✅ تم إعداد مجموعة المتاجر (vendors).<br>";

    // 3. مجموعة المنتجات (products)
    $productsCollection = $db->products;
    $productsCollection->createIndex(['vendor_id' => 1]);
    $productsCollection->createIndex(['category_id' => 1]);
    $productsCollection->createIndex(['name' => 'text', 'description' => 'text']); // للبحث النصي
    echo "✅ تم إعداد مجموعة المنتجات (products) وفهارس البحث.<br>";

    // 4. مجموعة الأقسام (categories)
    $categoriesCollection = $db->categories;
    // يمكننا إضافة أقسام افتراضية إذا كانت فارغة
    if ($categoriesCollection->countDocuments() === 0) {
        $categoriesCollection->insertMany([
            ['name' => 'إلكترونيات', 'icon' => 'fas fa-mobile-alt', 'created_at' => new MongoDB\BSON\UTCDateTime()],
            ['name' => 'ملابس', 'icon' => 'fas fa-tshirt', 'created_at' => new MongoDB\BSON\UTCDateTime()],
            ['name' => 'أحذية', 'icon' => 'fas fa-shoe-prints', 'created_at' => new MongoDB\BSON\UTCDateTime()],
            ['name' => 'منزل والديكور', 'icon' => 'fas fa-couch', 'created_at' => new MongoDB\BSON\UTCDateTime()],
        ]);
        echo "✅ تم إضافة أقسام افتراضية.<br>";
    } else {
        echo "✅ مجموعة الأقسام (categories) جاهزة.<br>";
    }

    // 5. مجموعة الطلبات (orders)
    $ordersCollection = $db->orders;
    $ordersCollection->createIndex(['customer_id' => 1]);
    $ordersCollection->createIndex(['created_at' => -1]);
    echo "✅ تم إعداد مجموعة الطلبات (orders).<br>";

    echo "<hr><h2 style='color:green'>🚀 مبروك! قاعدة البيانات (NoSQL) جاهزة للعمل.</h2>";
    echo "<p>تأكد من تفعيل إضافة <code>extension=mongodb</code> في ملف php.ini.</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='font-family:tahoma; direction:rtl; text-align:right; color:red; padding:20px;'>";
    echo "❌ حدث خطأ: " . $e->getMessage();
    echo "</div>";
}
?>