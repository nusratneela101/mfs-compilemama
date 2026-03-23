<?php
/**
 * Wallet — Transfer Page (wallet-to-wallet)
 * MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wallet.php';

startSecureSession();
requireSubscription();

$userId = (int)$_SESSION['user_id'];

if (!walletHasPin($userId)) {
    redirect('/wallet-setup.php');
}

$wallet    = getOrCreateWallet($userId);
$fee       = calculateFee($userId);
$error     = '';
$success   = '';
$txRef     = '';
$recipient = null;

// AJAX user lookup
if (isset($_GET['lookup_phone'])) {
    header('Content-Type: application/json; charset=utf-8');
    $phone = trim($_GET['lookup_phone']);
    if (!validateBDPhone($phone)) {
        echo json_encode(['found' => false, 'message' => 'অবৈধ নম্বর।']);
        exit;
    }
    $found = findUserByPhone($phone);
    if (!$found) {
        echo json_encode(['found' => false, 'message' => 'এই নম্বরে কোনো ব্যবহারকারী পাওয়া যায়নি।']);
        exit;
    }
    if ((int)$found['id'] === $userId) {
        echo json_encode(['found' => false, 'message' => 'নিজের নম্বরে ট্রান্সফার করা যাবে না।']);
        exit;
    }
    echo json_encode(['found' => true, 'name' => $found['name'], 'user_id' => $found['id']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf       = $_POST['csrf_token'] ?? '';
    $toPhone    = trim($_POST['recipient_phone'] ?? '');
    $toUserId   = (int)($_POST['recipient_user_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $pin        = $_POST['wallet_pin'] ?? '';

    if (!verifyCSRFToken($csrf)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।';
    } elseif (!validateBDPhone($toPhone) || !$toUserId) {
        $error = 'প্রাপকের সঠিক নম্বর দিন এবং যাচাই করুন।';
    } elseif ($amount < WALLET_MIN_AMOUNT || $amount > WALLET_MAX_AMOUNT) {
        $error = 'পরিমাণ ৳' . number_format(WALLET_MIN_AMOUNT, 0) . ' থেকে ৳' . number_format(WALLET_MAX_AMOUNT, 0) . ' এর মধ্যে হতে হবে।';
    } elseif (!verifyWalletPin($userId, $pin)) {
        $error = 'ওয়ালেট PIN সঠিক নয়।';
    } else {
        $recipient = findUserByPhone($toPhone);
        if (!$recipient || (int)$recipient['id'] !== $toUserId) {
            $error = 'প্রাপকের তথ্য যাচাই করতে পারা যায়নি।';
        } else {
            $result = transferMoney($userId, $toUserId, $amount);
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
}

$pageTitle = 'ট্রান্সফার';
$bodyClass = 'wallet-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width:560px">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/wallet.php" class="btn btn-outline-secondary rounded-circle p-2" style="width:40px;height:40px;line-height:1">
            ←
        </a>
        <div>
            <h4 class="fw-bold mb-0">🔄 ওয়ালেট ট্রান্সফার</h4>
            <small class="text-muted">অন্য ব্যবহারকারীর ওয়ালেটে টাকা পাঠান</small>
        </div>
    </div>

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
        <p class="small text-muted">সার্ভিস চার্জ: ৳<?= number_format($fee, 2) ?></p>
        <?php endif; ?>
        <p class="fw-bold">নতুন ব্যালেন্স: ৳<?= number_format((float)$wallet['balance'], 2) ?></p>
        <div class="d-flex justify-content-center gap-3 mt-3">
            <a href="/wallet-transfer.php" class="btn btn-primary rounded-pill">আবার পাঠান</a>
            <a href="/wallet.php" class="btn btn-outline-primary rounded-pill">ওয়ালেটে যান</a>
        </div>
    </div>
    <?php else: ?>
    <form method="post" autocomplete="off" id="transferForm">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="recipient_user_id" id="recipientUserId" value="">

        <!-- Recipient Search -->
        <div class="stat-card mb-3">
            <label class="form-label fw-semibold">প্রাপকের ফোন নম্বর</label>
            <div class="input-group mb-2">
                <input type="tel" name="recipient_phone" id="recipientPhone"
                       maxlength="11" inputmode="numeric"
                       class="form-control form-control-lg rounded-start-3"
                       placeholder="01XXXXXXXXX"
                       value="<?= sanitize($_POST['recipient_phone'] ?? '') ?>" required>
                <button type="button" class="btn btn-outline-primary" id="lookupBtn">
                    🔍 খুঁজুন
                </button>
            </div>
            <div id="recipientInfo" class="p-2 rounded-2 d-none">
                <!-- Filled by JS -->
            </div>
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
            <div class="mt-2 p-2 rounded-2 small <?= $fee > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' ?>">
                <?php if ($fee > 0): ?>
                ⚠️ সার্ভিস চার্জ: ৳<?= number_format($fee, 0) ?>
                <?php else: ?>
                🆓 এই লেনদেন ফ্রি!
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

        <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold fs-5" id="submitBtn" disabled>
            🔄 ট্রান্সফার করুন
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
const lookupBtn     = document.getElementById('lookupBtn');
const recipientInfo = document.getElementById('recipientInfo');
const userIdInput   = document.getElementById('recipientUserId');
const submitBtn     = document.getElementById('submitBtn');

<?php if (!empty($_POST['recipient_user_id'])): ?>
// Re-enable on form re-display after error
submitBtn.removeAttribute('disabled');
recipientInfo.classList.remove('d-none');
recipientInfo.innerHTML = '<span class="text-success fw-bold">✅ প্রাপক নিশ্চিত</span>';
<?php endif; ?>

lookupBtn?.addEventListener('click', async () => {
    const phone = document.getElementById('recipientPhone').value.trim();
    lookupBtn.disabled = true;
    lookupBtn.textContent = '...';
    recipientInfo.className = 'p-2 rounded-2 d-none';
    userIdInput.value = '';
    submitBtn.disabled = true;

    try {
        const res  = await fetch('/wallet-transfer.php?lookup_phone=' + encodeURIComponent(phone));
        const data = await res.json();
        recipientInfo.classList.remove('d-none');
        if (data.found) {
            recipientInfo.className = 'p-2 rounded-2 bg-success bg-opacity-10 text-success fw-bold';
            recipientInfo.innerHTML = '✅ ' + data.name + ' (' + phone + ')';
            userIdInput.value = data.user_id;
            submitBtn.disabled = false;
        } else {
            recipientInfo.className = 'p-2 rounded-2 bg-danger bg-opacity-10 text-danger';
            recipientInfo.innerHTML = '❌ ' + data.message;
        }
    } catch (e) {
        recipientInfo.className = 'p-2 rounded-2 bg-danger bg-opacity-10 text-danger';
        recipientInfo.innerHTML = '❌ সমস্যা হয়েছে।';
    } finally {
        lookupBtn.disabled = false;
        lookupBtn.textContent = '🔍 খুঁজুন';
    }
});

document.querySelectorAll('.quick-amount').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelector('[name="amount"]').value = btn.dataset.amount;
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
