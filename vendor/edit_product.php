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

    // التحقق من وجود معرف المنتج
    if (!isset($_GET['id'])) {
        header("Location: products.php");
        exit();
    }

    $product_id = new MongoDB\BSON\ObjectId($_GET['id']);
    $product = $db->products->findOne(['_id' => $product_id, 'vendor_id' => $vendor_id]);

    if (!$product) {
        die("خطأ: المنتج غير موجود أو لا تملك صلاحية تعديله.");
    }

    // 2. معالجة النموذج عند الإرسال
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = htmlspecialchars($_POST['name']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $category_id = new MongoDB\BSON\ObjectId($_POST['category_id']);
        $description = htmlspecialchars($_POST['description']);
        
        $updateData = [
            'name' => $name,
            'price' => $price,
            'stock' => $stock,
            'category_id' => $category_id,
            'description' => $description
        ];

        // معالجة الصورة (إذا تم رفع صورة جديدة)
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                if (!is_dir("../uploads/products")) {
                    mkdir("../uploads/products", 0777, true);
                }
                
                $new_filename = uniqid() . "." . $ext;
                $destination = "../uploads/products/" . $new_filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $updateData['image'] = "uploads/products/" . $new_filename;
                    
                    // حذف الصورة القديمة
                    if (isset($product['image']) && file_exists("../" . $product['image'])) {
                        unlink("../" . $product['image']);
                    }
                } else {
                    $msg = "فشل في رفع الصورة الجديدة.";
                    $msg_type = "error";
                }
            } else {
                $msg = "نوع الملف غير مدعوم.";
                $msg_type = "error";
            }
        }

        if (empty($msg)) {
            $db->products->updateOne(
                ['_id' => $product_id],
                ['$set' => $updateData]
            );
            
            $msg = "تم تحديث المنتج بنجاح!";
            $msg_type = "success";
            
            // تحديث البيانات المحلية للعرض
            $product = $db->products->findOne(['_id' => $product_id]);
        }
    }

    // 3. جلب الأقسام
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
    <title>تعديل منتج | لوحة التاجر</title>
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
                <h1 class="text-3xl font-bold mb-2">تعديل المنتج</h1>
                <p class="text-slate-400">تحديث تفاصيل المنتج: <?php echo htmlspecialchars($product['name']); ?></p>
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

        <!-- نموذج التعديل -->
        <form method="POST" enctype="multipart/form-data" class="glass-form rounded-2xl p-8 shadow-2xl">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- العمود الأيمن: البيانات الأساسية -->
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm text-slate-400 mb-2">اسم المنتج <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full p-3 rounded-xl input-dark">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">السعر (ر.ي) <span class="text-red-500">*</span></label>
                            <input type="number" name="price" value="<?php echo $product['price']; ?>" required class="w-full p-3 rounded-xl input-dark">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">الكمية المتوفرة <span class="text-red-500">*</span></label>
                            <input type="number" name="stock" value="<?php echo $product['stock']; ?>" required class="w-full p-3 rounded-xl input-dark">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">القسم <span class="text-red-500">*</span></label>
                        <select name="category_id" required class="w-full p-3 rounded-xl input-dark appearance-none">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['_id']; ?>" <?php echo $cat['_id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">وصف المنتج</label>
                        <textarea name="description" rows="4" class="w-full p-3 rounded-xl input-dark"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                </div>

                <!-- العمود الأيسر: الصورة -->
                <div class="flex flex-col">
                    <label class="block text-sm text-slate-400 mb-2">تحديث الصورة (اختياري)</label>
                    
                    <div class="border-2 border-dashed border-slate-600 rounded-2xl p-8 flex flex-col items-center justify-center text-center h-full hover:border-brand-gold transition-colors relative cursor-pointer" id="upload-box">
                        <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="previewImage(this)">
                        
                        <div id="placeholder" class="<?php echo isset($product['image']) ? 'hidden' : ''; ?> pointer-events-none">
                            <i class="fas fa-cloud-upload-alt text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400 font-bold">اضغط أو اسحب لتغيير الصورة</p>
                        </div>
                        
                        <img id="preview" src="../<?php echo isset($product['image']) ? $product['image'] : ''; ?>" class="<?php echo isset($product['image']) ? '' : 'hidden'; ?> max-h-64 rounded-xl object-contain shadow-lg" alt="معاينة">
                    </div>
                </div>

            </div>

            <!-- زر الحفظ -->
            <div class="mt-8 pt-6 border-t border-slate-700 flex justify-end">
                <button type="submit" class="bg-gradient-to-r from-brand-gold to-yellow-600 text-brand-dark font-bold py-3 px-8 rounded-xl hover:shadow-lg hover:shadow-yellow-500/20 transform hover:-translate-y-1 transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i> حفظ التعديلات
                </button>
            </div>

        </form>

    </div>

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
