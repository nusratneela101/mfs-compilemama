<?php
/**
 * Admin — My Profile / Password Change
 * MFS Compilemama
 */

$pageTitle = 'My Profile';
require_once __DIR__ . '/admin_header.php';

$db  = getDB();
$msg = '';
$err = '';

// Fetch current admin details
$stmt = $db->prepare("SELECT id, username, email, last_login, created_at FROM admin_users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['admin_id']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) {
    echo '<div class="alert alert-danger">Admin account not found.</div>';
    require_once __DIR__ . '/admin_footer.php';
    exit;
}

// ── Update profile ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (strlen($username) < 3 || strlen($username) > 50) {
            $err = 'Username must be 3–50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $err = 'Username may only contain letters, numbers and underscores.';
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email address.';
        } else {
            // Check duplicate
            $chk = $db->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
            $chk->bind_param('si', $username, $me['id']);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $err = 'Username already taken.';
            } else {
                $upd = $db->prepare("UPDATE admin_users SET username = ?, email = ? WHERE id = ?");
                $upd->bind_param('ssi', $username, $email, $me['id']);
                $upd->execute();
                $upd->close();
                $_SESSION['admin_username'] = $username;
                $me['username'] = $username;
                $me['email']    = $email;
                $msg = 'Profile updated successfully.';
            }
            $chk->close();
        }
    }
}

// ── Change password ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Fetch hash
        $hashRow = $db->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
        $hashRow->bind_param('i', $me['id']);
        $hashRow->execute();
        $hashData = $hashRow->get_result()->fetch_assoc();
        $hashRow->close();

        if (!password_verify($current, $hashData['password_hash'] ?? '')) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $err = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirm) {
            $err = 'New passwords do not match.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd  = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $upd->bind_param('si', $hash, $me['id']);
            $upd->execute();
            $upd->close();
            $msg = 'Password changed successfully.';
        }
    }
}
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= sanitize($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($err): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile Info Card -->
    <div class="col-lg-6">
        <div class="admin-stat-card border-left-0">
            <h5 class="fw-bold mb-4"><i class="fas fa-id-badge me-2 text-primary"></i>Profile Information</h5>
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control"
                           value="<?= sanitize($me['username']) ?>"
                           pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= sanitize($me['email'] ?? '') ?>" maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Account Created</label>
                    <p class="form-control-plaintext text-muted small"><?= formatDate($me['created_at']) ?></p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Last Login</label>
                    <p class="form-control-plaintext text-muted small"><?= $me['last_login'] ? formatDate($me['last_login']) : 'N/A' ?></p>
                </div>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i>Save Profile
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password Card -->
    <div class="col-lg-6">
        <div class="admin-stat-card border-left-0">
            <h5 class="fw-bold mb-4"><i class="fas fa-lock me-2 text-warning"></i>Change Password</h5>
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password">
                    <div class="form-text">Minimum 6 characters.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-warning px-4">
                    <i class="fas fa-key me-1"></i>Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
