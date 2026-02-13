# اختيار نسخة PHP الرسمية مع الأباتشي
FROM php:8.1-apache

# تثبيت الأدوات اللازمة لتعريف مكتبة MongoDB
RUN apt-get update && apt-get install -y \
    libssl-dev \
    unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# تفعيل ميزة الروابط الصديقة (Mod Rewrite) في أباتشي
RUN a2enmod rewrite

# نسخ ملفات مشروعك من جهازك إلى داخل السيرفر
COPY . /var/www/html/

# ضبط الصلاحيات (مهم للأمان السيبراني)
RUN chown -R www-data:www-data /var/www/html/

# تحديد المنفذ الذي سيعمل عليه السيرفر
EXPOSE 80