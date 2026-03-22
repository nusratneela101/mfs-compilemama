<?php
/**
 * OTP Verification Page — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) redirect('/dashboard.php');

$phone  = trim($_GET['phone'] ?? ($_SESSION['reg_phone'] ?? ''));
$from   = $_GET['from'] ?? 'login';
$error  = '';
$success = '';

if (!validateBDPhone($phone)) {
    redirect('/register.php');
}

// Handle resend OTP via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } elseif (!rateLimitCheck($phone, 'otp_send', RATE_LIMIT_OTP_PER_HOUR, 3600)) {
        $error = 'অনেকবার OTP পাঠানো হয়েছে। ১ ঘণ্টা পরে আবার চেষ্টা করুন।';
    } else {
        $db  = getDB();
        $otp = generateOTP();
        $exp = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
        $stmt = $db->prepare("INSERT INTO otp_codes (phone, code, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $phone, $otp, $exp);
        $stmt->execute();
        $stmt->close();
        sendOTP($phone, $otp);
        $success = 'নতুন OTP পাঠানো হয়েছে। ' . OTP_EXPIRY_MINUTES . ' মিনিটের মধ্যে ব্যবহার করুন।';
    }
}

// Handle OTP verify via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $token = $_POST['csrf_token'] ?? '';
    $code  = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');

    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } elseif (strlen($code) !== 6) {
        $error = '৬ সংখ্যার OTP কোড দিন।';
    } else {
        $db  = getDB();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare(
            "SELECT id FROM otp_codes WHERE phone = ? AND code = ? AND expires_at > ? AND verified = 0 ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param('sss', $phone, $code, $now);
        $stmt->execute();
        $otpRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$otpRow) {
            $error = 'OTP কোড ভুল অথবা মেয়াদ শেষ হয়ে গেছে।';
        } else {
            // Mark OTP as verified
            $stmt = $db->prepare("UPDATE otp_codes SET verified = 1 WHERE id = ?");
            $stmt->bind_param('i', $otpRow['id']);
            $stmt->execute();
            $stmt->close();

            // Activate user
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE phone = ?");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $stmt->close();

            // Auto login
            $stmt = $db->prepare("SELECT id, phone, name, pin_hash, status FROM users WHERE phone = ? LIMIT 1");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                login($user);
                redirect('/subscribe.php?msg=verified');
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle  = 'OTP যাচাই';
$bodyClass  = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="form-page-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">

                <div class="form-page-logo">
                    <div class="logo-icon">📱</div>
                    <h2>OTP যাচাই</h2>
                    <p class="text-muted">
                        <strong><?= sanitize($phone) ?></strong> নম্বরে পাঠানো
                        ৬ সংখ্যার কোড দিন
                    </p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-auto-dismiss rounded-3">
                    <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-auto-dismiss rounded-3">
                    <i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?>
                </div>
                <?php endif; ?>

                <div class="form-card text-center">
                    <!-- Timer -->
                    <div class="mb-4">
                        <div class="otp-timer" id="otpTimer"><?= str_pad(OTP_EXPIRY_MINUTES, 2, '0', STR_PAD_LEFT) ?>:00</div>
                        <small class="text-muted">সময় বাকি</small>
                    </div>

                    <form method="POST" action="/verify-otp.php?phone=<?= urlencode($phone) ?>&from=<?= urlencode($from) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="otp_code" id="otpHidden">

                        <div class="otp-inputs mb-4">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric" autocomplete="one-time-code">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric">
                            <input type="text" class="otp-input" maxlength="1" inputmode="numeric">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold mb-3"
                                id="verifyBtn">
                            <i class="fas fa-check-circle me-2"></i>যাচাই করুন
                        </button>
                    </form>

                    <!-- Resend form -->
                    <form method="POST" action="/verify-otp.php?phone=<?= urlencode($phone) ?>&from=<?= urlencode($from) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="resend" value="1">
                        <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill px-4"
                                id="resendOtpBtn" disabled>
                            <i class="fas fa-redo me-1"></i>পুনরায় OTP পাঠান
                        </button>
                    </form>

                    <div class="mt-3">
                        <small class="text-muted">
                            ভুল নম্বর?
                            <a href="/register.php" class="text-primary">পুনরায় রেজিস্টার করুন</a>
                        </small>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
