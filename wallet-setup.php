<?php
/**
 * Wallet PIN Setup — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wallet.php';

startSecureSession();
requireSubscription();

$userId  = (int)$_SESSION['user_id'];
$user    = getCurrentUser();
$wallet  = getOrCreateWallet($userId);
$hasPin  = walletHasPin($userId);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf   = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } else {
        $pin    = $_POST['pin'] ?? '';
        $pinC   = $_POST['pin_confirm'] ?? '';
        $oldPin = $_POST['old_pin'] ?? '';

        if (!ctype_digit($pin) || strlen($pin) < 4 || strlen($pin) > 6) {
            $error = 'PIN অবশ্যই ৪-৬ সংখ্যার হতে হবে।';
        } elseif ($pin !== $pinC) {
            $error = 'PIN মিলছে না।';
        } elseif ($hasPin && !verifyWalletPin($userId, $oldPin)) {
            $error = 'বর্তমান PIN সঠিক নয়।';
        } else {
            if (setWalletPin($userId, $pin)) {
                $success = 'ওয়ালেট PIN সফলভাবে ' . ($hasPin ? 'পরিবর্তন' : 'সেট') . ' হয়েছে।';
                $hasPin  = true;
            } else {
                $error = 'PIN সেট করতে সমস্যা হয়েছে।';
            }
        }
    }
}

$pageTitle = 'ওয়ালেট PIN সেটআপ';
$bodyClass = 'wallet-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5" style="max-width:480px">
    <div class="text-center mb-4">
        <div style="font-size:4rem">🔐</div>
        <h3 class="fw-bold"><?= $hasPin ? 'PIN পরিবর্তন করুন' : 'ওয়ালেট PIN সেট করুন' ?></h3>
        <p class="text-muted">সব ওয়ালেট লেনদেনে PIN প্রয়োজন হবে।</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success rounded-3">
        <?= sanitize($success) ?>
        <div class="mt-2"><a href="/wallet.php" class="btn btn-success btn-sm rounded-pill">ওয়ালেটে যান</a></div>
    </div>
    <?php endif; ?>

    <div class="stat-card">
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <?php if ($hasPin): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">বর্তমান PIN</label>
                <input type="password" name="old_pin" inputmode="numeric" maxlength="6"
                       class="form-control form-control-lg text-center rounded-3"
                       placeholder="••••" required>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold">নতুন PIN (৪–৬ সংখ্যা)</label>
                <input type="password" name="pin" inputmode="numeric" maxlength="6"
                       class="form-control form-control-lg text-center rounded-3"
                       placeholder="••••" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">PIN নিশ্চিত করুন</label>
                <input type="password" name="pin_confirm" inputmode="numeric" maxlength="6"
                       class="form-control form-control-lg text-center rounded-3"
                       placeholder="••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold fs-5">
                🔐 PIN <?= $hasPin ? 'পরিবর্তন' : 'সেট' ?> করুন
            </button>
        </form>

        <?php if ($hasPin): ?>
        <div class="text-center mt-3">
            <a href="/wallet.php" class="text-muted small">← ওয়ালেটে ফিরে যান</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
