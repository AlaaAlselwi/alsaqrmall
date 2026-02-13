    <!-- الفوتر (Footer) -->
    <footer class="bg-black pt-20 pb-10 border-t border-slate-800 mt-20">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <!-- عمود 1 -->
                <div>
                    <a href="#" class="text-3xl font-black tracking-tighter flex items-center gap-2 mb-6">
                        <i class="fas fa-eagle text-brand-gold"></i>
                        <span class="text-white">الصقر <span class="text-brand-gold">مول</span></span>
                    </a>
                    <p class="text-slate-400 mb-6 leading-relaxed">المنصة الأولى في اليمن التي تجمع بين الفخامة وسهولة التسوق. نضمن لك تجربة شراء آمنة وموثوقة.</p>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-brand-gold hover:text-brand-dark transition-all"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-brand-gold hover:text-brand-dark transition-all"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-brand-gold hover:text-brand-dark transition-all"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>

                <!-- عمود 2 -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-6">روابط سريعة</h4>
                    <ul class="space-y-4 text-slate-400">
                        <li><a href="#" class="hover:text-brand-gold transition-colors">عن الصقر مول</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors">كافة المتاجر</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors">العروض الخاصة</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors">اتصل بنا</a></li>
                    </ul>
                </div>

                <!-- عمود 3 -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-6">خدمة العملاء</h4>
                    <ul class="space-y-4 text-slate-400">
                        <li><a href="#" class="hover:text-brand-gold transition-colors">سياسة الخصوصية</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors">الشروط والأحكام</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors">سياسة الإرجاع</a></li>
                        <li><a href="#" class="hover:text-brand-gold transition-colors">الأسئلة الشائعة</a></li>
                    </ul>
                </div>

                <!-- عمود 4 -->
                <div>
                    <h4 class="text-white font-bold text-lg mb-6">تواصل معنا</h4>
                    <ul class="space-y-4 text-slate-400">
                        <li class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-brand-gold"></i> صنعاء، اليمن</li>
                        <li class="flex items-center gap-3"><i class="fas fa-phone text-brand-gold"></i> +967 770 000 000</li>
                        <li class="flex items-center gap-3"><i class="fas fa-envelope text-brand-gold"></i> support@alsaqrmall.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-slate-800 pt-8 text-center text-slate-500 text-sm">
                &copy; <?php echo date('Y'); ?> جميع الحقوق محفوظة لـ الصقر مول.
            </div>
        </div>
    </footer>

    <!-- تفعيل مكتبة الحركات -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true, // الحركة تعمل مرة واحدة فقط عند النزول
            offset: 100, // مسافة البدء
        });

        // تغيير خلفية الناف بار عند النزول
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (navbar) {
                if (window.scrollY > 50) {
                    navbar.classList.add('shadow-lg');
                } else {
                    navbar.classList.remove('shadow-lg');
                }
            }
        });
    </script>
</body>
</html>
