<?php
require_once __DIR__ . '/../vendor/autoload.php'; // تأكد من أن المسار صحيح لملف الـ autoload

use MongoDB\Client;

class Database {
    private static $client = null;
    private static $dbName = 'alsaqrmall_nosql'; // اسم قاعدة البيانات الجديدة

    public static function connect() {
        if (self::$client === null) {
            try {
                // الاتصال بـ MongoDB
                // يفترض أن MongoDB يعمل على المنفذ الافتراضي 27017
                self::$client = new Client("mongodb+srv://alaalselwi40_db_user:Alaa780766@alaa.bcrayia.mongodb.net/alsaqrmall_nosql?retryWrites=true&w=majority");
            } catch (Exception $e) {
                die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
            }
        }
        return self::$client->selectDatabase(self::$dbName);
    }
}
?>
