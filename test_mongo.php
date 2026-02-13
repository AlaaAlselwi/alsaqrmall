<?php
try {
    // محاول الاتصال المباشر بدون مكتبة (Extension only)
    $manager = new MongoDB\Driver\Manager("mongodb://127.0.0.1:27017");
    $command = new MongoDB\Driver\Command(['ping' => 1]);
    $cursor = $manager->executeCommand('admin', $command);
    $response = $cursor->toArray()[0];
    
    echo "<h1>✅ الاتصال نجح!</h1>";
    echo "<pre>";
    print_r($response);
    echo "</pre>";
    
} catch (MongoDB\Driver\Exception\Exception $e) {
    echo "<h1>❌ خطأ في الاتصال بالسيرفر</h1>";
    echo "يبدو أن خدمة MongoDB (Server) لا تعمل على جهازك.<br>";
    echo "الرسالة التقنية: " . $e->getMessage();
}
?>
