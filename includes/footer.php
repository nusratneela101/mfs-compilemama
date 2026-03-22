<?php
// footer.php — settings vars are already loaded by header.php via includes/settings.php
// Use dynamic values with config fallbacks
$_footerSiteName   = isset($_siteName)    ? $_siteName    : SITE_NAME;
$_footerSiteNameBn = isset($_siteNameBn)  ? $_siteNameBn  : SITE_NAME_BN;
$_footerText       = getSetting('footer_text',   '© ' . date('Y') . ' ' . $_footerSiteName . '. All rights reserved.');
$_footerEmail      = getSetting('contact_email', 'support@mfscompilemama.com');
$_footerPhone      = getSetting('contact_phone', '');
$_facebookUrl      = getSetting('facebook_url',  '');
$_whatsappNum      = getSetting('whatsapp_number', '');
$_telegramUrl      = getSetting('telegram_url',  '');
$_subPrice         = getSetting('subscription_price', (string)SUB_AMOUNT);
$_currencySymbol   = getSetting('currency_symbol', '৳');
?>
<!-- Footer -->
<footer class="mfs-footer mt-auto">
    <div class="container">
        <div class="row g-4 py-5">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?php if (isset($_logo) && $_logo): ?>
                        <img src="<?= sanitize($_logo) ?>" alt="<?= sanitize($_footerSiteName) ?>" style="max-height:40px;width:auto">
                    <?php else: ?>
                        <span style="font-size:2rem">💳</span>
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><?= sanitize($_footerSiteName) ?></h5>
                        <small class="text-white-50"><?= sanitize($_footerSiteNameBn) ?></small>
                    </div>
                </div>
                <p class="text-white-50 small">
                    বাংলাদেশের সকল মোবাইল ফিনান্সিয়াল সার্ভিস একটি প্ল্যাটফর্মে।
                    নিরাপদ, সহজ এবং দ্রুত লেনদেন।
                </p>
                <?php if ($_facebookUrl || $_whatsappNum || $_telegramUrl): ?>
                <div class="d-flex gap-2 mt-2">
                    <?php if ($_facebookUrl): ?>
                    <a href="<?= sanitize($_facebookUrl) ?>" class="btn btn-sm btn-outline-light rounded-circle" target="_blank" rel="noopener">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($_whatsappNum): ?>
                    <a href="https://wa.me/<?= sanitize(preg_replace('/[^0-9]/', '', $_whatsappNum)) ?>" class="btn btn-sm btn-outline-light rounded-circle" target="_blank" rel="noopener">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($_telegramUrl): ?>
                    <a href="<?= sanitize($_telegramUrl) ?>" class="btn btn-sm btn-outline-light rounded-circle" target="_blank" rel="noopener">
                        <i class="fab fa-telegram-plane"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
                    <?php if ($_footerEmail): ?>
                    <li class="mt-2"><i class="fas fa-envelope me-2"></i><?= sanitize($_footerEmail) ?></li>
                    <?php endif; ?>
                    <?php if ($_footerPhone): ?>
                    <li class="mt-2"><i class="fas fa-phone me-2"></i><?= sanitize($_footerPhone) ?></li>
                    <?php endif; ?>
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
                    <?= sanitize($_footerText) ?>
                </small>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <small class="text-white-50">
                    🇧🇩 Made with ❤️ for Bangladesh | সাবস্ক্রিপশন: <?= sanitize($_currencySymbol) ?><?= sanitize($_subPrice) ?>/মাস
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
