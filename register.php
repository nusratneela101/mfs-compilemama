<?php
/**
 * Registration Page — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) redirect('/dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ। পুনরায় চেষ্টা করুন।';
    } else {
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $pin         = $_POST['pin'] ?? '';
        $pinConfirm  = $_POST['pin_confirm'] ?? '';

        // Validate
        if (empty($name) || strlen($name) < 2) {
            $error = 'সঠিক নাম দিন (কমপক্ষে ২ অক্ষর)।';
        } elseif (!validateBDPhone($phone)) {
            $error = 'সঠিক বাংলাদেশি ফোন নম্বর দিন (01XXXXXXXXX)।';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'সঠিক ইমেইল ঠিকানা দিন।';
        } elseif (strlen($pin) < PIN_MIN_LENGTH || strlen($pin) > PIN_MAX_LENGTH || !ctype_digit($pin)) {
            $error = PIN_MIN_LENGTH . '–' . PIN_MAX_LENGTH . ' সংখ্যার PIN দিন।';
        } elseif ($pin !== $pinConfirm) {
            $error = 'PIN এবং কনফার্ম PIN মিলছে না।';
        } elseif (!rateLimitCheck($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'register', 5, 3600)) {
            $error = 'অনেকবার চেষ্টা করা হয়েছে। ১ ঘণ্টা পরে আবার চেষ্টা করুন।';
        } else {
            $db = getDB();
            // Check duplicate phone
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($dup) {
                $error = 'এই ফোন নম্বরটি ইতিমধ্যে নিবন্ধিত।';
            } else {
                $hash  = hashPIN($pin);
                $emailVal = $email ?: null;
                $stmt  = $db->prepare(
                    "INSERT INTO users (phone, pin_hash, name, email, status) VALUES (?, ?, ?, ?, 'inactive')"
                );
                $stmt->bind_param('ssss', $phone, $hash, $name, $emailVal);
                if ($stmt->execute()) {
                    $stmt->close();
                    // Generate & send OTP
                    $otp   = generateOTP();
                    $exp   = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
                    $stmtO = $db->prepare("INSERT INTO otp_codes (phone, code, expires_at) VALUES (?, ?, ?)");
                    $stmtO->bind_param('sss', $phone, $otp, $exp);
                    $stmtO->execute();
                    $stmtO->close();

                    sendOTP($phone, $otp);

                    $_SESSION['reg_phone'] = $phone;
                    redirect('/verify-otp.php?phone=' . urlencode($phone) . '&from=register');
                } else {
                    $error = 'রেজিস্ট্রেশন ব্যর্থ হয়েছে। পুনরায় চেষ্টা করুন।';
                    $stmt->close();
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle  = 'রেজিস্টার';
$bodyClass  = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="form-page-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="form-page-logo">
                    <div class="logo-icon">💳</div>
                    <h2><?= SITE_NAME ?></h2>
                    <p class="text-muted">নতুন অ্যাকাউন্ট তৈরি করুন</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-auto-dismiss rounded-3">
                    <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
                </div>
                <?php endif; ?>

                <div class="form-card">
                    <form method="POST" action="/register.php" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-user me-1 text-primary"></i>সম্পূর্ণ নাম
                            </label>
                            <input type="text" class="form-control" id="name" name="name"
                                   placeholder="আপনার নাম" required minlength="2" maxlength="100"
                                   value="<?= sanitize($_POST['name'] ?? '') ?>">
                            <div class="invalid-feedback">সঠিক নাম দিন।</div>
                        </div>

                        <div class="mb-3">
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

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1 text-primary"></i>ইমেইল (ঐচ্ছিক)
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="example@email.com" maxlength="150"
                                   value="<?= sanitize($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="pin" class="form-label">
                                <i class="fas fa-lock me-1 text-primary"></i>PIN
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="pin" name="pin"
                                       placeholder="<?= PIN_MIN_LENGTH ?>–<?= PIN_MAX_LENGTH ?> সংখ্যার PIN"
                                       minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                                       required inputmode="numeric" pattern="[0-9]{<?= PIN_MIN_LENGTH ?>,<?= PIN_MAX_LENGTH ?>}">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePassword('pin',this)" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback"><?= PIN_MIN_LENGTH ?>–<?= PIN_MAX_LENGTH ?> সংখ্যার PIN দিন।</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="pin_confirm" class="form-label">
                                <i class="fas fa-lock me-1 text-primary"></i>PIN নিশ্চিত করুন
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="pin_confirm" name="pin_confirm"
                                       placeholder="PIN পুনরায় লিখুন"
                                       minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                                       required inputmode="numeric">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePassword('pin_confirm',this)" tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">PIN মিলছে না।</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold">
                            <i class="fas fa-user-plus me-2"></i>রেজিস্টার করুন
                        </button>
                    </form>

                    <hr class="my-4">
                    <div class="text-center">
                        <p class="mb-0 text-muted">
                            ইতিমধ্যে অ্যাকাউন্ট আছে?
                            <a href="/login.php" class="fw-bold text-primary">লগইন করুন</a>
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
