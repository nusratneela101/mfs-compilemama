<?php
/**
 * Homepage — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';

startSecureSession();
$isLogged  = isLoggedIn();
$providers = getMFSProviders();
$pageTitle = 'হোম';
$bodyClass = 'home-page';

// Load dynamic hero & about content (with fallbacks)
$heroContent  = getSiteContent('hero');
$aboutContent = getSiteContent('about');
$_heroTitle   = $heroContent['title']       ?? SITE_NAME;
$_heroSub     = $heroContent['subtitle']    ?? 'সব MFS একটি প্ল্যাটফর্মে';
$_heroDesc    = $heroContent['description'] ?? '';
$_heroImage   = $heroContent['image']       ?? '';
$_aboutTitle  = $aboutContent['title']       ?? 'আমাদের সম্পর্কে';
$_aboutDesc   = $aboutContent['description'] ?? '';

// Dynamic subscription price & currency from settings
$_subPrice  = getSetting('subscription_price', (string)SUB_AMOUNT);
$_currency  = getSetting('currency_symbol', '৳');

include __DIR__ . '/includes/header.php';
?>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="text-center">
        <div class="loader-icon">💳</div>
        <div class="loader-text"><?= sanitize($_siteName ?? SITE_NAME) ?></div>
    </div>
</div>

<!-- ============================================================
     Hero Section
     ============================================================ -->
<section class="hero-section"<?php if ($_heroImage): ?> style="background-image:url('<?= sanitize($_heroImage) ?>');background-size:cover;background-position:center"<?php endif; ?>>
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-badge">
                    🇧🇩 বাংলাদেশের #১ MFS পোর্টাল
                </div>
                <h1 class="hero-title"><?= sanitize($_heroTitle) ?></h1>
                <p class="hero-subtitle-bn"><?= sanitize($_heroSub) ?></p>
                <p class="hero-desc">
                    <?php if ($_heroDesc): ?>
                        <?= nl2br(sanitize($_heroDesc)) ?>
                    <?php else: ?>
                        বাংলাদেশের সকল মোবাইল ফিনান্সিয়াল সার্ভিস — bKash, Nagad, Rocket এবং আরও ১০টি —
                        একটি সুন্দর, নিরাপদ এবং সহজ প্ল্যাটফর্মে ব্যবহার করুন।
                        মাত্র <?= sanitize($_currency) ?><?= sanitize($_subPrice) ?>/মাসে সম্পূর্ণ অ্যাক্সেস পান।
                    <?php endif; ?>
                </p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-num">১৩</div>
                        <div class="hero-stat-label">MFS সার্ভিস</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-num">৫টি</div>
                        <div class="hero-stat-label">সেবার ধরন</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-num"><?= sanitize($_currency) ?><?= sanitize($_subPrice) ?></div>
                        <div class="hero-stat-label">মাসিক সাবস্ক্রিপশন</div>
                    </div>
                </div>
                <div class="hero-cta-group">
                    <?php if ($isLogged): ?>
                        <a href="/dashboard.php" class="btn-hero-primary">
                            <i class="fas fa-th-large me-2"></i>ড্যাশবোর্ডে যান
                        </a>
                        <a href="/mfs-portal.php" class="btn-hero-outline">
                            <i class="fas fa-mobile-alt me-2"></i>MFS পোর্টাল
                        </a>
                    <?php else: ?>
                        <a href="/register.php" class="btn-hero-primary">
                            <i class="fas fa-user-plus me-2"></i>বিনামূল্যে রেজিস্টার করুন
                        </a>
                        <a href="/login.php" class="btn-hero-outline">
                            <i class="fas fa-sign-in-alt me-2"></i>লগইন করুন
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="hero-phone-mockup" style="font-size:12rem;line-height:1;filter:drop-shadow(0 20px 40px rgba(0,0,0,0.3));">
                    📱
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     MFS Providers Grid
     ============================================================ -->
<section class="mfs-grid-section" id="providers">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">সকল MFS সার্ভিস</h2>
            <p class="section-subtitle">বাংলাদেশের ১৩টি MFS কোম্পানি — একটি পোর্টালে</p>
        </div>

        <div class="row g-3 g-md-4">
            <?php foreach ($providers as $p):
                $link = $isLogged ? '/mfs-portal.php?provider=' . urlencode($p['slug']) : '/login.php';
            ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="<?= sanitize($link) ?>" class="mfs-card"
                   style="--card-color: <?= sanitize($p['color']) ?>"
                   title="<?= sanitize($p['name']) ?> — <?= sanitize($p['name_bn']) ?>">
                    <span class="mfs-card-icon"><?= $p['icon'] ?></span>
                    <div class="mfs-card-name"><?= sanitize($p['name']) ?></div>
                    <div class="mfs-card-name-bn"><?= sanitize($p['name_bn']) ?></div>
                    <span class="mfs-card-badge" style="background:<?= sanitize($p['color']) ?>22;color:<?= sanitize($p['color']) ?>">
                        Active
                    </span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$isLogged): ?>
        <div class="text-center mt-5">
            <p class="text-muted mb-3">সকল সার্ভিস অ্যাক্সেস করতে লগইন বা রেজিস্টার করুন</p>
            <a href="/register.php" class="btn btn-primary btn-lg px-5 rounded-pill me-2">
                <i class="fas fa-user-plus me-2"></i>রেজিস্টার করুন
            </a>
            <a href="/login.php" class="btn btn-outline-primary btn-lg px-5 rounded-pill">
                <i class="fas fa-sign-in-alt me-2"></i>লগইন
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     Features Section
     ============================================================ -->
<section class="features-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">কেন MFS Compilemama?</h2>
            <p class="section-subtitle">আমাদের বিশেষত্ব</p>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['icon'=>'🔒','title'=>'সম্পূর্ণ নিরাপদ','desc'=>'bcrypt এনক্রিপশন, CSRF সুরক্ষা এবং OTP ভেরিফিকেশন সহ আপনার তথ্য সম্পূর্ণ সুরক্ষিত।'],
                ['icon'=>'⚡','title'=>'দ্রুত লেনদেন','desc'=>'মাত্র কয়েক সেকেন্ডে সেন্ড মানি, ক্যাশ আউট, রিচার্জ এবং পেমেন্ট সম্পন্ন করুন।'],
                ['icon'=>'📱','title'=>'মোবাইল ফার্স্ট','desc'=>'সব ডিভাইসে সুন্দরভাবে কাজ করে — মোবাইল, ট্যাবলেট বা ডেস্কটপ।'],
                ['icon'=>'🇧🇩','title'=>'বাংলাদেশ কেন্দ্রিক','desc'=>'বাংলা ভাষায় ইন্টারফেস, বাংলাদেশি নম্বর ফরম্যাট সমর্থন।'],
                ['icon'=>'📊','title'=>'লেনদেন ইতিহাস','desc'=>'সকল MFS এর লেনদেন একটি জায়গায় দেখুন এবং ট্র্যাক করুন।'],
                ['icon'=>'💎','title'=>'সাশ্রয়ী মূল্য','desc'=>'মাত্র ' . sanitize($_currency) . sanitize($_subPrice) . '/মাসে ১৩টি MFS সার্ভিসের সম্পূর্ণ অ্যাক্সেস।'],
            ];
            foreach ($features as $f): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon-wrap">
                        <span style="font-size:2rem"><?= $f['icon'] ?></span>
                    </div>
                    <h5 class="fw-bold mb-2"><?= $f['title'] ?></h5>
                    <p class="text-muted small mb-0"><?= $f['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     How It Works
     ============================================================ -->
<section class="how-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">কিভাবে শুরু করবেন?</h2>
            <p class="section-subtitle">মাত্র ৪টি ধাপে শুরু করুন</p>
        </div>
        <div class="row g-4 text-center">
            <?php
            $steps = [
                ['num'=>'1','icon'=>'📝','title'=>'রেজিস্টার করুন','desc'=>'আপনার ফোন নম্বর ও PIN দিয়ে অ্যাকাউন্ট তৈরি করুন।'],
                ['num'=>'2','icon'=>'📱','title'=>'OTP ভেরিফাই','desc'=>'SMS এ আসা ৬ সংখ্যার কোড দিয়ে নম্বর নিশ্চিত করুন।'],
                ['num'=>'3','icon'=>'💳','title'=>'সাবস্ক্রাইব করুন','desc'=>sanitize($_currency) . sanitize($_subPrice) . ' বিকাশ/নগদে পাঠিয়ে সাবস্ক্রিপশন নিন।'],
                ['num'=>'4','icon'=>'🚀','title'=>'ব্যবহার শুরু করুন','desc'=>'১৩টি MFS সার্ভিসের সব সুবিধা উপভোগ করুন!'],
            ];
            foreach ($steps as $s): ?>
            <div class="col-6 col-md-3">
                <div class="step-card">
                    <div class="step-num"><?= $s['num'] ?></div>
                    <div style="font-size:2.5rem;margin-bottom:12px"><?= $s['icon'] ?></div>
                    <h5 class="fw-bold"><?= $s['title'] ?></h5>
                    <p class="text-muted small"><?= $s['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <div class="d-none d-md-flex justify-content-center gap-5 align-items-center" style="height:4px;">
                <!-- decorative connector -->
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     Pricing Section
     ============================================================ -->
<section class="pricing-section" id="pricing">
    <div class="container">
        <div class="text-center text-white mb-5">
            <h2 class="section-title">সাবস্ক্রিপশন প্ল্যান</h2>
            <p style="opacity:.8">সহজ এবং সাশ্রয়ী মূল্য</p>
        </div>
        <div class="pricing-card">
            <div class="hero-badge mb-2" style="background:rgba(255,255,255,0.2)">⭐ সব ফিচার অন্তর্ভুক্ত</div>
            <h3 class="text-white">মাসিক প্ল্যান</h3>
            <div class="pricing-price"><?= sanitize($_currency) ?><?= sanitize($_subPrice) ?></div>
            <div class="pricing-price-sub">প্রতি মাস (<?= SUB_DAYS ?> দিন)</div>
            <ul class="pricing-features mt-4">
                <?php
                $pfeat = [
                    '✅ ১৩টি MFS সার্ভিসের সম্পূর্ণ অ্যাক্সেস',
                    '✅ সেন্ড মানি, ক্যাশ আউট, রিচার্জ',
                    '✅ মার্চেন্ট পেমেন্ট',
                    '✅ ব্যালেন্স চেক',
                    '✅ লেনদেন ইতিহাস',
                    '✅ নিরাপদ ও এনক্রিপ্টেড',
                    '✅ ২৪/৭ অ্যাক্সেস',
                ];
                foreach ($pfeat as $pf): ?>
                <li><?= $pf ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= $isLogged ? '/subscribe.php' : '/register.php' ?>" 
               class="btn btn-light text-primary fw-bold w-100 btn-lg rounded-pill">
                <?= $isLogged ? 'এখনই সাবস্ক্রাইব করুন' : 'শুরু করুন' ?>
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
