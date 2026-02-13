<?php
// 1. كود المعالجة الخلفية (PHP Backend)
require_once 'vendor/autoload.php'; // استدعاء Composer Autoload
require_once 'includes/db.php'; // استدعاء ملف الاتصال

use Mpdf\Mpdf;

$message = "";
$msg_type = ""; // success or error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db = Database::connect();
        $usersCollection = $db->users;
        $vendorsCollection = $db->vendors;

        // استقبال البيانات وتنظيفها
        $first_name = htmlspecialchars($_POST['first_name']);
        $last_name = htmlspecialchars($_POST['last_name']);
        $phone = htmlspecialchars($_POST['phone']);
        $password_raw = $_POST['password'];
        $role = $_POST['role']; // customer or vendor

        // التحقق من الحقول الأساسية
        if (empty($first_name) || empty($last_name) || empty($phone) || empty($password_raw)) {
            throw new Exception("جميع الحقول الأساسية مطلوبة.");
        }

        // التحقق من تكرار رقم الهاتف
        $existingUser = $usersCollection->findOne(['phone' => $phone]);
        if ($existingUser) {
            throw new Exception("رقم الهاتف هذا مسجل مسبقاً.");
        }

        // تشفير كلمة المرور
        $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);

        // تجهيز وثيقة المستخدم
        $userDocument = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'password' => $hashed_password,
            'role' => $role,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ];

        // 1. إدخال المستخدم
        $insertResult = $usersCollection->insertOne($userDocument);
        $user_id = $insertResult->getInsertedId();
        $user_id_str = (string)$user_id;

        // 2. معالجة وثائق التاجر وإنشاء PDF
        if ($role === 'vendor') {
            $store_name = htmlspecialchars($_POST['store_name']);
            $store_type = htmlspecialchars($_POST['store_type']);
            $location = htmlspecialchars($_POST['location']);
            $description = htmlspecialchars($_POST['description']);

            if (empty($store_name)) {
                $usersCollection->deleteOne(['_id' => $user_id]);
                throw new Exception("اسم المتجر مطلوب للتجار.");
            }

            // إنشاء مجلد للتاجر
            $uploadDir = "uploads/vendors/" . $user_id_str . "/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // دالة مساعدة لرفع الصور
            function uploadImage($fileInputName, $targetDir, $prefix) {
                if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] != UPLOAD_ERR_OK) {
                    throw new Exception("فشل رفع الملف: $fileInputName");
                }
                $fileExt = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($fileExt, $allowed)) {
                    throw new Exception("نوع الملف غير مدعوم: $fileInputName");
                }
                $fileName = $prefix . '_' . time() . '.' . $fileExt;
                $targetFile = $targetDir . $fileName;
                if (!move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFile)) {
                    throw new Exception("فشل نقل الملف: $fileName");
                }
                return $targetFile;
            }

            // رفع الملفات
            $idFrontPath = uploadImage('id_front', $uploadDir, 'id_front');
            $idBackPath = uploadImage('id_back', $uploadDir, 'id_back');
            $idSelfiePath = uploadImage('id_selfie', $uploadDir, 'id_selfie');
            $crPath = uploadImage('commercial_reg', $uploadDir, 'commercial_reg');

            // إنشاء ملف PDF
            $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
            $mpdf->SetDirectionality('rtl');
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;

            $html = "
            <style>
                body { font-family: 'dejavusans'; direction: rtl; text-align: right; }
                h1 { color: #fbbf24; text-align: center; }
                .section { margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                .label { font-weight: bold; }
                img { max-width: 100%; height: auto; border: 1px solid #333; margin-top: 5px; }
            </style>
            <h1>ملف اعتماد التاجر</h1>
            <div class='section'>
                <h2>بيانات المتجر والمالك</h2>
                <p><span class='label'>اسم المالك:</span> $first_name $last_name</p>
                <p><span class='label'>اسم المتجر:</span> $store_name</p>
                <p><span class='label'>نوع التجارة:</span> $store_type</p>
                <p><span class='label'>رقم الهاتف:</span> $phone</p>
                <p><span class='label'>الموقع:</span> $location</p>
                <p><span class='label'>الوصف:</span> $description</p>
            </div>
            
            <div class='section'>
                <h2>البطاقة الشخصية (أمامية)</h2>
                <img src='$idFrontPath' style='width: 300px;'>
            </div>
            
            <div class='section'>
                <h2>البطاقة الشخصية (خلفية)</h2>
                <img src='$idBackPath' style='width: 300px;'>
            </div>

            <div class='section'>
                <h2>صورة شخصية مع البطاقة</h2>
                <img src='$idSelfiePath' style='width: 300px;'>
            </div>

            <div class='section'>
                <h2>السجل التجاري</h2>
                <img src='$crPath' style='width: 300px;'>
            </div>
            ";

            $mpdf->WriteHTML($html);
            $pdfPath = $uploadDir . 'vendor_profile.pdf';
            $mpdf->Output($pdfPath, 'F');

            $vendorDocument = [
                'user_id' => $user_id,
                'store_name' => $store_name,
                'store_type' => $store_type,
                'location' => $location,
                'description' => $description,
                'status' => 'pending',
                'documents' => [
                    'id_front' => $idFrontPath,
                    'id_back' => $idBackPath,
                    'id_selfie' => $idSelfiePath,
                    'commercial_reg' => $crPath,
                    'profile_pdf' => $pdfPath
                ],
                'logo' => null,
                'cover_image' => null,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];

            $vendorsCollection->insertOne($vendorDocument);
        }

        $message = "تم إنشاء الحساب بنجاح! " . ($role === 'vendor' ? "تم استلام وثائقك للمراجعة." : "يمكنك تسجيل الدخول الآن.");
        $msg_type = "success";

    } catch (Exception $e) {
        // إذا حدث خطأ وكان المستخدم قد تم إنشاؤه للتو، نحاول حذفه (Clean up scope)
        // هذا بسيط هنا، في الإنتاج قد نحتاج transaction أو Saga
        if (isset($usersCollection) && isset($user_id)) {
             $usersCollection->deleteOne(['_id' => $user_id]);
             // أيضاً حذف المجلد إذا تم إنشاؤه
             if (isset($uploadDir) && is_dir($uploadDir)) {
                 // حذف محتويات المجلد أولاً (بشكل مبسط)
                 array_map('unlink', glob("$uploadDir/*.*"));
                 rmdir($uploadDir);
             }
        }
        $message = "خطأ: " . $e->getMessage();
        $msg_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد | الصقر مول</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-gold': '#fbbf24',
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-field {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: #fbbf24;
            box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.1);
            outline: none;
        }
        .file-upload-box {
            border: 2px dashed rgba(251, 191, 36, 0.3);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload-box:hover {
            border-color: #fbbf24;
            background: rgba(251, 191, 36, 0.05);
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans min-h-screen flex items-center justify-center p-4 py-10 relative overflow-x-hidden">

    <!-- رسائل التنبيه -->
    <?php if(!empty($message)): ?>
    <div class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md p-4 rounded-xl shadow-2xl <?php echo $msg_type == 'success' ? 'bg-green-600' : 'bg-red-600'; ?> text-white text-center font-bold animate-bounce">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <div class="fixed inset-0 z-0 pointer-events-none">
        <div class="absolute top-[-10%] right-[-5%] w-96 h-96 bg-brand-gold rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-pulse"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-pulse" style="animation-delay: 2s"></div>
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
    </div>

    <div class="relative z-10 w-full max-w-5xl grid grid-cols-1 md:grid-cols-2 gap-0 glass-panel rounded-3xl overflow-hidden shadow-2xl my-auto" data-aos="zoom-in" data-aos-duration="800">
        
        <!-- القسم الأيمن -->
        <div class="hidden md:flex flex-col justify-center items-center p-8 lg:p-12 relative bg-gradient-to-br from-brand-dark to-slate-900 border-l border-white/5">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1556742049-0cfed4f7a07d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80')] bg-cover bg-center opacity-20 mix-blend-overlay"></div>
            <div class="relative z-10 text-center">
                <div class="w-20 h-20 bg-brand-gold/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-brand-gold/20">
                    <i class="fas fa-eagle text-brand-gold text-4xl"></i>
                </div>
                <h2 class="text-4xl font-bold mb-4">انضم لعائلة الصقر</h2>
                <p class="text-slate-400 mb-8">سواء كنت تبحث عن أفضل المنتجات، أو تريد توسيع تجارتك، مكانك هنا.</p>
            </div>
        </div>

        <!-- القسم الأيسر: النموذج -->
        <div class="p-6 sm:p-8 lg:p-12 flex flex-col justify-center w-full">
            <div class="text-center mb-6 lg:mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">إنشاء حساب جديد</h1>
                <p class="text-slate-500 text-sm">أدخل بياناتك لتبدأ الرحلة</p>
            </div>

            <!-- أزرار التبديل -->
            <div class="flex p-1 bg-slate-800/50 rounded-xl mb-6 lg:mb-8 border border-white/5 relative">
                <button type="button" onclick="switchTab('customer')" id="tab-customer" class="flex-1 py-3 text-sm font-bold rounded-lg transition-all duration-300 flex items-center justify-center gap-2 bg-brand-gold text-brand-dark shadow-lg">
                    <i class="fas fa-user"></i> أنا زبون
                </button>
                <button type="button" onclick="switchTab('vendor')" id="tab-vendor" class="flex-1 py-3 text-sm font-bold rounded-lg transition-all duration-300 flex items-center justify-center gap-2 text-slate-400 hover:text-white">
                    <i class="fas fa-store"></i> أنا تاجر
                </button>
            </div>

            <!-- بداية النموذج الفعلي -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="space-y-4 lg:space-y-5">
                <!-- حقل مخفي لتخزين الدور (customer/vendor) -->
                <input type="hidden" name="role" id="role-input" value="customer">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="group">
                        <label class="block text-xs text-slate-400 mb-1">الاسم الأول</label>
                        <input type="text" name="first_name" required class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm" placeholder="محمد">
                    </div>
                    <div class="group">
                        <label class="block text-xs text-slate-400 mb-1">الاسم الأخير</label>
                        <input type="text" name="last_name" required class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm" placeholder="أحمد">
                    </div>
                </div>

                <div class="group">
                    <label class="block text-xs text-slate-400 mb-1">رقم الهاتف</label>
                    <input type="tel" name="phone" required class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm" placeholder="77xxxxxxx">
                </div>

                <div class="group">
                    <label class="block text-xs text-slate-400 mb-1">كلمة المرور</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm" placeholder="••••••••">
                </div>

                <!-- حقول التاجر -->
                <div id="vendor-fields" class="hidden space-y-4 lg:space-y-5 border-l-2 border-brand-gold pl-4 ml-1 transition-all">
                    <div class="group">
                        <label class="block text-xs text-brand-gold mb-1 font-bold">اسم المتجر <span class="text-red-500">*</span></label>
                        <input type="text" name="store_name" class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm" placeholder="مثال: متجر الأناقة">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="group">
                            <label class="block text-xs text-brand-gold mb-1 font-bold">نوع التجارة</label>
                            <select name="store_type" class="w-full px-4 py-3 rounded-xl input-field text-slate-300 bg-slate-900 text-sm">
                                <option value="electronics">إلكترونيات</option>
                                <option value="fashion">ملابس وأزياء</option>
                                <option value="beauty">عطور وتجميل</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="group">
                            <label class="block text-xs text-brand-gold mb-1 font-bold">الموقع</label>
                            <input type="text" name="location" class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm" placeholder="صنعاء، شارع...">
                        </div>
                    </div>

                    <div class="group">
                        <label class="block text-xs text-brand-gold mb-1 font-bold">وصف المتجر</label>
                        <textarea name="description" rows="2" class="w-full px-4 py-3 rounded-xl input-field text-white placeholder-slate-600 text-sm"></textarea>
                    </div>

                    <h3 class="text-sm font-bold text-white border-b border-brand-gold/30 pb-2 mb-2">الوثائق المطلوبة (صور واضحة)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="file-upload-box relative">
                            <input type="file" name="id_front" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <i class="far fa-id-card text-2xl text-slate-400 mb-2"></i>
                            <div class="text-xs text-slate-300">البطاقة الأمامية <span class="text-red-500">*</span></div>
                        </div>
                        <div class="file-upload-box relative">
                            <input type="file" name="id_back" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <i class="fas fa-id-card text-2xl text-slate-400 mb-2"></i>
                            <div class="text-xs text-slate-300">البطاقة الخلفية <span class="text-red-500">*</span></div>
                        </div>
                        <div class="file-upload-box relative">
                            <input type="file" name="id_selfie" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <i class="fas fa-user-check text-2xl text-slate-400 mb-2"></i>
                            <div class="text-xs text-slate-300">صورة لك مع البطاقة <span class="text-red-500">*</span></div>
                        </div>
                        <div class="file-upload-box relative">
                            <input type="file" name="commercial_reg" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <i class="fas fa-file-contract text-2xl text-slate-400 mb-2"></i>
                            <div class="text-xs text-slate-300">السجل التجاري <span class="text-red-500">*</span></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 lg:py-4 bg-gradient-to-r from-brand-gold to-yellow-600 text-brand-dark font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 mt-6 text-sm lg:text-base">
                    <span id="btn-text">إنشاء حساب زبون</span> <i class="fas fa-arrow-left mr-2"></i>
                </button>

                <div class="text-center text-sm text-slate-400 mt-6">
                    لديك حساب بالفعل؟ <a href="login.php" class="text-brand-gold font-bold underline">سجل الدخول هنا</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
        function switchTab(type) {
            const customerTab = document.getElementById('tab-customer');
            const vendorTab = document.getElementById('tab-vendor');
            const vendorFields = document.getElementById('vendor-fields');
            const btnText = document.getElementById('btn-text');
            const roleInput = document.getElementById('role-input');
            
            // حقول مطلوبة للتاجر (لرفع خاصية required عند التبديل للزبون)
            const vendorRequiredInputs = document.querySelectorAll('#vendor-fields input[type="file"], #vendor-fields input[name="store_name"]');

            if (type === 'vendor') {
                vendorTab.classList.add('bg-brand-gold', 'text-brand-dark', 'shadow-lg');
                vendorTab.classList.remove('text-slate-400');
                customerTab.classList.remove('bg-brand-gold', 'text-brand-dark', 'shadow-lg');
                customerTab.classList.add('text-slate-400');
                vendorFields.classList.remove('hidden');
                vendorFields.classList.add('block');
                btnText.textContent = "تسجيل متجر جديد";
                roleInput.value = "vendor";
                
                vendorRequiredInputs.forEach(input => input.setAttribute('required', 'true'));
            } else {
                customerTab.classList.add('bg-brand-gold', 'text-brand-dark', 'shadow-lg');
                customerTab.classList.remove('text-slate-400');
                vendorTab.classList.remove('bg-brand-gold', 'text-brand-dark', 'shadow-lg');
                vendorTab.classList.add('text-slate-400');
                vendorFields.classList.add('hidden');
                vendorFields.classList.remove('block');
                btnText.textContent = "إنشاء حساب زبون";
                roleInput.value = "customer";

                vendorRequiredInputs.forEach(input => input.removeAttribute('required'));
            }
        }
    </script>
</body>
</html>