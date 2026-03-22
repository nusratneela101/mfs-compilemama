<?php
/**
 * Email Verification Page — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email.php';

startSecureSession();

if (isLoggedIn()) redirect('/dashboard.php');

$token = trim($_GET['token'] ?? '');
$error   = '';
$success = '';

if (empty($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) {
    $error = 'ভেরিফিকেশন লিংক অবৈধ।';
} else {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare(
        "SELECT ev.*, u.name, u.email AS user_email FROM email_verifications ev
         JOIN users u ON u.id = ev.user_id
         WHERE ev.token = ? AND ev.used = 0 AND ev.expires_at > ?
         LIMIT 1"
    );
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // Check if token exists but is already used or expired
        $stmtC = $db->prepare("SELECT used, expires_at FROM email_verifications WHERE token = ? LIMIT 1");
        $stmtC->bind_param('s', $token);
        $stmtC->execute();
        $existing = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();

        if ($existing && $existing['used']) {
            $error = 'এই ভেরিফিকেশন লিংকটি ইতিমধ্যে ব্যবহার করা হয়েছে। লগইন করুন।';
        } elseif ($existing) {
            $error = 'ভেরিফিকেশন লিংকের মেয়াদ শেষ হয়ে গেছে। পুনরায় রেজিস্ট্রেশন করুন।';
        } else {
            $error = 'ভেরিফিকেশন লিংক অবৈধ বা খুঁজে পাওয়া যায়নি।';
        }
    } else {
        // Mark token as used
        $stmtU = $db->prepare("UPDATE email_verifications SET used = 1 WHERE id = ?");
        $stmtU->bind_param('i', $row['id']);
        $stmtU->execute();
        $stmtU->close();

        // Activate user
        $stmtA = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'inactive'");
        $stmtA->bind_param('i', $row['user_id']);
        $stmtA->execute();
        $stmtA->close();

        $success = true;
    }
}

$pageTitle = 'ইমেইল ভেরিফিকেশন';
$bodyClass = 'auth-page';
include __DIR__ . '/includes/header.php';
?>

<div class="form-page-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="form-page-logo">
                    <div class="logo-icon">✉️</div>
                    <h2><?= SITE_NAME ?></h2>
                </div>

                <?php if ($success): ?>
                <div class="form-card text-center">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                    <h3 class="fw-bold text-success mb-3">অ্যাকাউন্ট ভেরিফাই হয়েছে!</h3>
                    <p class="text-muted mb-4">
                        আপনার ইমেইল সফলভাবে যাচাই হয়েছে। এখন লগইন করুন।
                    </p>
                    <a href="/login.php?msg=registered" class="btn btn-primary btn-lg rounded-pill fw-bold px-5">
                        <i class="fas fa-sign-in-alt me-2"></i>লগইন করুন
                    </a>
                </div>
                <?php else: ?>
                <div class="form-card text-center">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">❌</div>
                    <h3 class="fw-bold text-danger mb-3">ভেরিফিকেশন ব্যর্থ</h3>
                    <p class="text-muted mb-4"><?= sanitize($error) ?></p>
                    <a href="/register.php" class="btn btn-primary rounded-pill fw-bold px-5">
                        <i class="fas fa-user-plus me-2"></i>পুনরায় রেজিস্টার করুন
                    </a>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
