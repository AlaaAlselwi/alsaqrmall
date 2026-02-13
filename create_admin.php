<?php
require_once 'includes/db.php';

try {
    $db = Database::connect();
    $usersCollection = $db->users;

    $adminPhone = '777777777';
    $password = 'admin123';

    // التحقق مما إذا كان الأدمن موجوداً
    $admin = $usersCollection->findOne(['phone' => $adminPhone]);

    if ($admin) {
        echo "<div dir='rtl' style='font-family:tahoma; padding:20px; color:red;'>";
        echo "⚠️ حساب الأدمن موجود مسبقاً!";
        echo "</div>";
    } else {
        $adminData = [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'phone' => $adminPhone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        $usersCollection->insertOne($adminData);
        
        echo "<div dir='rtl' style='font-family:tahoma; padding:20px; background:#e0f7fa; border:1px solid #0097a7; border-radius:10px;'>";
        echo "✅ تم إنشاء حساب الأدمن بنجاح!<br><br>";
        echo "<b>رقم الهاتف:</b> $adminPhone<br>";
        echo "<b>كلمة المرور:</b> $password<br>";
        echo "<br><a href='login.php'>تسجيل الدخول</a>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
