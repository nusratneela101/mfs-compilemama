<?php
/**
 * Wallet — Withdraw Page
 * MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wallet.php';

startSecureSession();
requireSubscription();

$userId    = (int)$_SESSION['user_id'];
$providers = getMFSProviders();

if (!walletHasPin($userId)) {
    redirect('/wallet-setup.php');
}

$wallet  = getOrCreateWallet($userId);
$fee     = calculateFee($userId);
$error   = '';
$success = '';
$txRef   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf       = $_POST['csrf_token'] ?? '';
    $mfsSlug    = $_POST['mfs_provider'] ?? '';
    $mfsAccount = trim($_POST['mfs_account'] ?? '');
    $amount     = (float)($_POST['amount'] ?? 0);
    $pin        = $_POST['wallet_pin'] ?? '';

    if (!verifyCSRFToken($csrf)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } elseif (!$mfsSlug || !getMFSProvider($mfsSlug)) {
        $error = 'MFS প্রদানকারী বেছে নিন।';
    } elseif (!validateBDPhone($mfsAccount)) {
        $error = 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন (01XXXXXXXXX)।';
    } elseif ($amount < WALLET_MIN_AMOUNT || $amount > WALLET_MAX_AMOUNT) {
        $error = 'পরিমাণ ৳' . number_format(WALLET_MIN_AMOUNT, 0) . ' থেকে ৳' . number_format(WALLET_MAX_AMOUNT, 0) . ' এর মধ্যে হতে হবে।';
    } elseif (!verifyWalletPin($userId, $pin)) {
        $error = 'ওয়ালেট PIN সঠিক নয়।';
    } else {
        $mfsProvider = getMFSProvider($mfsSlug);
        $result = withdrawMoney($userId, $amount, $mfsProvider['name'], $mfsAccount);
        if ($result['success']) {
            $success = $result['message'];
            $txRef   = $result['reference_id'];
            $fee     = $result['fee'];
            $wallet  = getOrCreateWallet($userId);
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'উইথড্রো';
$bodyClass = 'wallet-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width:600px">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/wallet.php" class="btn btn-outline-secondary rounded-circle p-2" style="width:40px;height:40px;line-height:1">
            ←
        </a>
        <div>
            <h4 class="fw-bold mb-0">📤 উইথড্রো</h4>
            <small class="text-muted">ওয়ালেট থেকে যেকোনো MFS এ টাকা পাঠান</small>
        </div>
    </div>

    <!-- Balance -->
    <div class="rounded-3 p-3 mb-4 text-white text-center"
         style="background:linear-gradient(135deg,#E2136E,#8B1A7C)">
        <div class="opacity-75 small">বর্তমান ব্যালেন্স</div>
        <div class="fw-bold fs-4">৳<?= number_format((float)$wallet['balance'], 2) ?></div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="stat-card text-center py-5">
        <div style="font-size:4rem" class="mb-3">✅</div>
        <h4 class="fw-bold text-success"><?= sanitize($success) ?></h4>
        <p class="text-muted">রেফারেন্স: <strong><?= sanitize($txRef) ?></strong></p>
        <?php if ($fee > 0): ?>
        <p class="small text-muted">সার্ভিস চার্জ কাটা হয়েছে: ৳<?= number_format($fee, 2) ?></p>
        <?php endif; ?>
        <p class="fw-bold">নতুন ব্যালেন্স: ৳<?= number_format((float)$wallet['balance'], 2) ?></p>
        <div class="d-flex justify-content-center gap-3 mt-3">
            <a href="/wallet-withdraw.php" class="btn btn-primary rounded-pill">আবার উইথড্রো</a>
            <a href="/wallet.php" class="btn btn-outline-primary rounded-pill">ওয়ালেটে যান</a>
        </div>
    </div>
    <?php else: ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <!-- MFS Provider -->
        <div class="stat-card mb-3">
            <h6 class="fw-bold mb-3">গন্তব্য MFS প্রদানকারী বেছে নিন</h6>
            <div class="row g-2">
                <?php foreach ($providers as $p): ?>
                <div class="col-4 col-sm-3">
                    <label class="d-block cursor-pointer">
                        <input type="radio" name="mfs_provider" value="<?= sanitize($p['slug']) ?>"
                               class="d-none mfs-radio"
                               <?= (($_POST['mfs_provider'] ?? '') === $p['slug']) ? 'checked' : '' ?>>
                        <div class="mfs-select-card rounded-3 p-2 text-center border"
                             style="--card-color:<?= sanitize($p['color']) ?>;cursor:pointer;transition:.2s">
                            <div style="font-size:1.6rem"><?= $p['icon'] ?></div>
                            <div style="font-size:.72rem;font-weight:600"><?= sanitize($p['name']) ?></div>
                            <div style="font-size:.65rem;color:#888"><?= sanitize($p['name_bn']) ?></div>
                        </div>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recipient MFS Number -->
        <div class="stat-card mb-3">
            <label class="form-label fw-semibold">প্রাপকের MFS নম্বর</label>
            <input type="tel" name="mfs_account" maxlength="11" inputmode="numeric"
                   class="form-control form-control-lg rounded-3"
                   placeholder="01XXXXXXXXX"
                   value="<?= sanitize($_POST['mfs_account'] ?? '') ?>" required>
            <small class="text-muted">বাংলাদেশি ফরম্যাটে ১১ সংখ্যার নম্বর</small>
        </div>

        <!-- Amount -->
        <div class="stat-card mb-3">
            <label class="form-label fw-semibold">পরিমাণ (৳)</label>
            <div class="input-group mb-2">
                <span class="input-group-text fw-bold">৳</span>
                <input type="number" name="amount" min="<?= WALLET_MIN_AMOUNT ?>"
                       max="<?= WALLET_MAX_AMOUNT ?>" step="1"
                       class="form-control form-control-lg rounded-end-3"
                       placeholder="পরিমাণ লিখুন"
                       value="<?= sanitize($_POST['amount'] ?? '') ?>" required>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach ([100, 200, 500, 1000, 2000, 5000] as $q): ?>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill quick-amount"
                        data-amount="<?= $q ?>">
                    ৳<?= number_format($q) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <!-- Fee and balance notice -->
            <div class="mt-2 p-2 rounded-2 small <?= $fee > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' ?>">
                <?php if ($fee > 0): ?>
                ⚠️ সার্ভিস চার্জ: ৳<?= number_format($fee, 0) ?> — মোট কাটবে: <span id="totalDeduct">—</span>
                <?php else: ?>
                🆓 এই লেনদেন ফ্রি! মোট কাটবে: <span id="totalDeduct">—</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Wallet PIN -->
        <div class="stat-card mb-4">
            <label class="form-label fw-semibold">🔐 ওয়ালেট PIN</label>
            <input type="password" name="wallet_pin" inputmode="numeric" maxlength="6"
                   class="form-control form-control-lg text-center rounded-3"
                   placeholder="••••" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold fs-5">
            📤 উইথড্রো করুন
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.quick-amount').forEach(btn => {
    btn.addEventListener('click', () => {
        const amtInput = document.querySelector('[name="amount"]');
        amtInput.value = btn.dataset.amount;
        amtInput.dispatchEvent(new Event('input'));
    });
});

document.querySelector('[name="amount"]')?.addEventListener('input', function () {
    const amt  = parseFloat(this.value) || 0;
    const fee  = <?= $fee ?>;
    const total = amt + fee;
    const el   = document.getElementById('totalDeduct');
    if (el) el.textContent = '৳' + total.toLocaleString('bn-BD', {minimumFractionDigits: 2});
});

document.querySelectorAll('.mfs-radio').forEach(radio => {
    const card = radio.nextElementSibling;
    if (radio.checked) card.style.borderColor = 'var(--card-color)';
    radio.addEventListener('change', () => {
        document.querySelectorAll('.mfs-select-card').forEach(c => {
            c.style.borderColor = '';
            c.style.background  = '';
        });
        if (radio.checked) {
            card.style.borderColor = 'var(--card-color)';
            card.style.background  = 'rgba(226,19,110,.06)';
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
