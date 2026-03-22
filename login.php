<?php
/**
 * Login Page — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error   = '';
$success = '';

if (isset($_GET['msg'])) {
    $msgs = [
        'logged_out'     => 'আপনি সফলভাবে লগআউট করেছেন।',
        'registered'     => 'রেজিস্ট্রেশন সফল! এখন লগইন করুন।',
        'verify_first'   => 'লগইন করার আগে OTP ভেরিফাই করুন।',
        'session_expired'=> 'সেশন শেষ হয়ে গেছে। পুনরায় লগইন করুন।',
    ];
    $key = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
    if (isset($msgs[$key])) $success = $msgs[$key];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ। পুনরায় চেষ্টা করুন।';
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $pin   = $_POST['pin'] ?? '';

        if (!validateBDPhone($phone)) {
            $error = 'সঠিক বাংলাদেশি ফোন নম্বর দিন (01XXXXXXXXX)।';
        } elseif (strlen($pin) < PIN_MIN_LENGTH || strlen($pin) > PIN_MAX_LENGTH) {
            $error = PIN_MIN_LENGTH . '–' . PIN_MAX_LENGTH . ' সংখ্যার PIN দিন।';
        } elseif (!rateLimitCheck($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'login', RATE_LIMIT_LOGIN_PER_HOUR, 3600)) {
            $error = 'অনেকবার চেষ্টা করা হয়েছে। ১ ঘণ্টা পরে আবার চেষ্টা করুন।';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, phone, name, pin_hash, status FROM users WHERE phone = ? LIMIT 1");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = 'ফোন নম্বর বা PIN সঠিক নয়।';
            } elseif ($user['status'] === 'blocked') {
                $error = 'আপনার অ্যাকাউন্ট ব্লক করা হয়েছে। সাপোর্টে যোগাযোগ করুন।';
            } elseif ($user['status'] === 'inactive') {
                // Check if OTP verified
                $stmtO = $db->prepare("SELECT id FROM otp_codes WHERE phone = ? AND verified = 1 LIMIT 1");
                $stmtO->bind_param('s', $phone);
                $stmtO->execute();
                $otpRow = $stmtO->get_result()->fetch_assoc();
                $stmtO->close();
                if (!$otpRow) {
                    redirect('/verify-otp.php?phone=' . urlencode($phone));
                }
                $error = 'আপনার অ্যাকাউন্ট সক্রিয় নেই।';
            } elseif (!verifyPIN($pin, $user['pin_hash'])) {
                $error = 'ফোন নম্বর বা PIN সঠিক নয়।';
            } else {
                login($user);
                $redirect = $_GET['redirect'] ?? '/dashboard.php';
                // Sanitize redirect URL to prevent open redirect
                if (!preg_match('/^\/[a-zA-Z0-9\/_\-\.?=&%]+$/', $redirect)) {
                    $redirect = '/dashboard.php';
                }
                redirect($redirect);
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = 'লগইন';
$bodyClass = 'auth-page';

include __DIR__ . '/includes/header.php';
?>

<div class="form-page-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">

                <div class="form-page-logo">
                    <div class="logo-icon">💳</div>
                    <h2><?= SITE_NAME ?></h2>
                    <p class="text-muted">আপনার অ্যাকাউন্টে লগইন করুন</p>
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

                <div class="form-card">
                    <form method="POST" action="/login.php" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <div class="mb-4">
                            <label for="phone" class="form-label">
                                <i class="fas fa-mobile-alt me-1 text-primary"></i>ফোন নম্বর
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">🇧🇩 +880</span>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       placeholder="01XXXXXXXXX" maxlength="11" required
                                       pattern="01[3-9][0-9]{8}"
                                       value="<?= sanitize($_POST['phone'] ?? '') ?>">
                                <div class="invalid-feedback">সঠিক বাংলাদেশি নম্বর দিন।</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="pin" class="form-label">
                                <i class="fas fa-lock me-1 text-primary"></i>PIN
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="pin" name="pin"
                                       placeholder="আপনার PIN" minlength="<?= PIN_MIN_LENGTH ?>"
                                       maxlength="<?= PIN_MAX_LENGTH ?>" required inputmode="numeric">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePassword('pin',this)" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback"><?= PIN_MIN_LENGTH ?>–<?= PIN_MAX_LENGTH ?> সংখ্যার PIN দিন।</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold">
                            <i class="fas fa-sign-in-alt me-2"></i>লগইন করুন
                        </button>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="mb-0 text-muted">
                            অ্যাকাউন্ট নেই?
                            <a href="/register.php" class="fw-bold text-primary">রেজিস্টার করুন</a>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
