<!-- Footer -->
<footer class="mfs-footer mt-auto">
    <div class="container">
        <div class="row g-4 py-5">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span style="font-size:2rem">💳</span>
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><?= SITE_NAME ?></h5>
                        <small class="text-white-50"><?= SITE_NAME_BN ?></small>
                    </div>
                </div>
                <p class="text-white-50 small">
                    বাংলাদেশের সকল মোবাইল ফিনান্সিয়াল সার্ভিস একটি প্ল্যাটফর্মে।
                    নিরাপদ, সহজ এবং দ্রুত লেনদেন।
                </p>
            </div>
            <div class="col-lg-2 col-6">
                <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="/" class="text-white-50 text-decoration-none hover-white">হোম</a></li>
                    <li><a href="/login.php" class="text-white-50 text-decoration-none hover-white">লগইন</a></li>
                    <li><a href="/register.php" class="text-white-50 text-decoration-none hover-white">রেজিস্টার</a></li>
                    <li><a href="/subscribe.php" class="text-white-50 text-decoration-none hover-white">সাবস্ক্রাইব</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-6">
                <h6 class="text-white fw-bold mb-3">সেবাসমূহ</h6>
                <ul class="list-unstyled small">
                    <li><span class="text-white-50">💸 সেন্ড মানি</span></li>
                    <li><span class="text-white-50">🏧 ক্যাশ আউট</span></li>
                    <li><span class="text-white-50">📱 মোবাইল রিচার্জ</span></li>
                    <li><span class="text-white-50">🏪 পেমেন্ট</span></li>
                    <li><span class="text-white-50">💰 ব্যালেন্স চেক</span></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="text-white fw-bold mb-3">যোগাযোগ</h6>
                <ul class="list-unstyled small text-white-50">
                    <li><i class="fas fa-map-marker-alt me-2"></i>ঢাকা, বাংলাদেশ</li>
                    <li class="mt-2"><i class="fas fa-envelope me-2"></i>support@mfscompilemama.com</li>
                    <li class="mt-2">
                        <span class="badge bg-success">✅ সক্রিয় সেবা</span>
                    </li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary">
        <div class="row align-items-center py-3">
            <div class="col-md-6 text-center text-md-start">
                <small class="text-white-50">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. সর্বস্বত্ব সংরক্ষিত।
                </small>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <small class="text-white-50">
                    🇧🇩 Made with ❤️ for Bangladesh | সাবস্ক্রিপশন: ৳<?= SUB_AMOUNT ?>/মাস
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Main App JS -->
<script src="/assets/js/app.js"></script>

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:9999"></div>

</body>
</html>
