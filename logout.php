<?php
session_start();

// تفريغ جميع متغيرات الجلسة
$_SESSION = array();

// إذا كنت تريد تدمير الجلسة بالكامل، احذف أيضًا ملف تعريف ارتباط الجلسة (cookie)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه إلى الصفحة الرئيسية أو صفحة تسجيل الدخول
header("Location: index.php");
exit;
?>
