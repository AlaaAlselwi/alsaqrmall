<?php
session_start();
require_once '../includes/db.php';

// 1. حماية الصفحة
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

try {
    $db = Database::connect();

    // جلب معرف المتجر (Vendor ID)
    $user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
    $vendor = $db->vendors->findOne(['user_id' => $user_id]);

    if (!$vendor) {
        die("خطأ: لم يتم العثور على حساب التاجر.");
    }
    
    $vendor_id = $vendor['_id'];

    // 2. معالجة النموذج عند الإرسال
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = htmlspecialchars($_POST['name']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $category_id = new MongoDB\BSON\ObjectId($_POST['category_id']);
        $description = htmlspecialchars($_POST['description']);
        
        // معالجة الصورة
        $image_path = "";
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['image']['name'];
            $filetype = $_FILES['image']['type'];
            $filesize = $_FILES['image']['size'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                // إنشاء مجلد الصور إذا لم يكن موجوداً
                if (!is_dir("../uploads/products")) {
                    mkdir("../uploads/products", 0777, true);
                }
                
                // تسمية الصورة بشكل فريد لتجنب التكرار
                $new_filename = uniqid() . "." . $ext;
                $destination = "../uploads/products/" . $new_filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $image_path = "uploads/products/" . $new_filename; // المسار للحفظ في القاعدة
                } else {
                    $msg = "فشل في رفع الصورة.";
                    $msg_type = "error";
                }
            } else {
                $msg = "نوع الملف غير مدعوم. يرجى رفع صورة (JPG, PNG, WEBP).";
                $msg_type = "error";
            }
        }

        if (empty($msg) && !empty($image_path)) {
            $productDocument = [
                'vendor_id' => $vendor_id,
                'category_id' => $category_id,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stock' => $stock,
                'image' => $image_path,
                'views' => 0,
                'is_featured' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ];

            $db->products->insertOne($productDocument);
            
            $msg = "تم إضافة المنتج بنجاح!";
            $msg_type = "success";
        } elseif (empty($image_path) && empty($msg)) {
             $msg = "صورة المنتج مطلوبة.";
             $msg_type = "error";
        }
    }

    // 3. جلب الأقسام للقائمة المنسدلة
    $categories = $db->categories->find()->toArray();

} catch(Exception $e) {
    $msg = "خطأ: " . $e->getMessage();
    $msg_type = "error";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة منتج | لوحة التاجر</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-dark': '#0f172a',
                        'brand-gold': '#fbbf24',
                        'brand-accent': '#3b82f6',
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .glass-form {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .input-dark {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .input-dark:focus {
            border-color: #fbbf24;
            outline: none;
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2);
        }
    </style>
</head>
<body class="bg-brand-dark text-white font-sans min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-4xl">
        
        <!-- رأس الصفحة -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold mb-2">إضافة منتج جديد</h1>
                <p class="text-slate-400">أضف تفاصيل منتجك ليبدأ العملاء بالشراء.</p>
            </div>
            <a href="products.php" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <i class="fas fa-arrow-right"></i> عودة للمنتجات
            </a>
        </div>

        <!-- رسائل التنبيه -->
        <?php if(!empty($msg)): ?>
        <div class="mb-6 p-4 rounded-xl font-bold text-center <?php echo $msg_type == 'success' ? 'bg-green-600/20 text-green-500 border border-green-500/30' : 'bg-red-600/20 text-red-500 border border-red-500/30'; ?>">
            <?php echo $msg; ?>
        </div>
        <?php endif; ?>

        <!-- نموذج الإضافة -->
        <form method="POST" enctype="multipart/form-data" class="glass-form rounded-2xl p-8 shadow-2xl">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- العمود الأيمن: البيانات الأساسية -->
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm text-slate-400 mb-2">اسم المنتج <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required class="w-full p-3 rounded-xl input-dark" placeholder="مثال: سماعة بلوتوث رياضية">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">السعر (ر.ي) <span class="text-red-500">*</span></label>
                            <input type="number" name="price" required class="w-full p-3 rounded-xl input-dark" placeholder="5000">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">الكمية المتوفرة <span class="text-red-500">*</span></label>
                            <input type="number" name="stock" required class="w-full p-3 rounded-xl input-dark" placeholder="10">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">القسم <span class="text-red-500">*</span></label>
                        <select name="category_id" required class="w-full p-3 rounded-xl input-dark appearance-none">
                            <option value="">اختر القسم...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">وصف المنتج</label>
                        <textarea name="description" rows="4" class="w-full p-3 rounded-xl input-dark" placeholder="اكتب وصفاً جذاباً للمنتج..."></textarea>
                    </div>
                </div>

                <!-- العمود الأيسر: الصورة -->
                <div class="flex flex-col">
                    <label class="block text-sm text-slate-400 mb-2">صورة المنتج الرئيسية <span class="text-red-500">*</span></label>
                    
                    <div class="border-2 border-dashed border-slate-600 rounded-2xl p-8 flex flex-col items-center justify-center text-center h-full hover:border-brand-gold transition-colors relative cursor-pointer" id="upload-box">
                        <input type="file" name="image" required accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewImage(this)">
                        
                        <div id="placeholder" class="pointer-events-none">
                            <i class="fas fa-cloud-upload-alt text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400 font-bold">اضغط أو اسحب الصورة هنا</p>
                            <p class="text-slate-600 text-sm mt-2">JPG, PNG, WEBP (Max 2MB)</p>
                        </div>
                        
                        <img id="preview" class="hidden max-h-64 rounded-xl object-contain shadow-lg" alt="معاينة">
                    </div>
                </div>

            </div>

            <!-- زر الحفظ -->
            <div class="mt-8 pt-6 border-t border-slate-700 flex justify-end">
                <button type="submit" class="bg-gradient-to-r from-brand-gold to-yellow-600 text-brand-dark font-bold py-3 px-8 rounded-xl hover:shadow-lg hover:shadow-yellow-500/20 transform hover:-translate-y-1 transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i> نشر المنتج
                </button>
            </div>

        </form>

    </div>

    <!-- سكربت معاينة الصورة -->
    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const placeholder = document.getElementById('placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>