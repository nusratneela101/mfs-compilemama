<?php
/**
 * Admin Login — MFS Compilemama
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

if (isAdminLoggedIn()) redirect('/admin/index.php');

$error = '';
$msg   = $_GET['msg'] ?? '';
$successMsg = $msg === 'logged_out' ? 'সফলভাবে লগআউট হয়েছেন।' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } elseif (!rateLimitCheck($_SERVER['REMOTE_ADDR'] ?? 'unknown', 'admin_login', 5, 900)) {
        $error = 'অনেকবার চেষ্টা করা হয়েছে। ১৫ মিনিট পরে আবার চেষ্টা করুন।';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $error = 'ব্যবহারকারীর নাম বা পাসওয়ার্ড সঠিক নয়।';
        } else {
            loginAdmin($admin);
            redirect('/admin/index.php');
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page" style="background:linear-gradient(135deg,#1a0033 0%,#E2136E 100%);min-height:100vh;display:flex;align-items:center;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-4">
            <div class="text-center mb-4">
                <div style="font-size:3rem">🛡️</div>
                <h2 class="text-white fw-bold"><?= SITE_NAME ?></h2>
                <p class="text-white-50">Admin Panel</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger rounded-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($successMsg): ?>
            <div class="alert alert-success rounded-3"><?= sanitize($successMsg) ?></div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" action="/admin/login.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-user me-1 text-primary"></i>Username</label>
                        <input type="text" class="form-control" name="username" required
                               value="<?= sanitize($_POST['username'] ?? '') ?>" placeholder="admin">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold"><i class="fas fa-lock me-1 text-primary"></i>Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="adminPass" name="password" required placeholder="••••••••">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('adminPass',this)" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold">
                        <i class="fas fa-sign-in-alt me-2"></i>লগইন
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="/" class="text-muted small"><i class="fas fa-home me-1"></i>মূল সাইটে ফিরুন</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    input.type  = input.type === 'password' ? 'text' : 'password';
    btn.innerHTML = input.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
