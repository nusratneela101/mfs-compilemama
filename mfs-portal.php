<?php
/**
 * MFS Portal — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/subscription.php';

startSecureSession();
requireSubscription();

$userId    = (int)$_SESSION['user_id'];
$providers = getMFSProviders();

// Determine selected provider
$slug     = trim($_GET['provider'] ?? $_POST['mfs_provider'] ?? '');
$provider = $slug ? getMFSProvider($slug) : null;
if (!$provider && !empty($providers)) {
    $provider = $providers[0];
}

$defaultAction = htmlspecialchars($_GET['action'] ?? 'send', ENT_QUOTES, 'UTF-8');
$allowedActions = ['send','cashout','recharge','payment','balance'];
if (!in_array($defaultAction, $allowedActions, true)) $defaultAction = 'send';

$pageTitle = 'MFS পোর্টাল' . ($provider ? ' — ' . $provider['name'] : '');
$bodyClass  = 'portal-page';

// Recent transactions for this provider
$db    = getDB();
$stmt  = $db->prepare(
    "SELECT * FROM transactions WHERE user_id = ?" .
    ($provider ? " AND mfs_provider = ?" : "") .
    " ORDER BY created_at DESC LIMIT 8"
);
if ($provider) {
    $stmt->bind_param('is', $userId, $provider['slug']);
} else {
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$recentTx = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrfToken  = generateCSRFToken();
$typeLabels = ['send'=>'সেন্ড মানি','cashout'=>'ক্যাশ আউট','recharge'=>'মোবাইল রিচার্জ','payment'=>'পেমেন্ট','balance'=>'ব্যালেন্স চেক'];
$typeIcons  = ['send'=>'📤','cashout'=>'🏧','recharge'=>'📱','payment'=>'🏪','balance'=>'💰'];
$statusCls  = ['success'=>'badge-success','pending'=>'badge-pending','failed'=>'badge-failed'];

include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Provider Header -->
    <?php if ($provider): ?>
    <div class="portal-header mb-4 rounded-4 text-white"
         style="background: linear-gradient(135deg, <?= sanitize($provider['color']) ?>, <?= sanitize($provider['color']) ?>cc)">
        <div class="row align-items-center">
            <div class="col">
                <div class="d-flex align-items-center gap-3">
                    <span style="font-size:3rem"><?= $provider['icon'] ?></span>
                    <div>
                        <h3 class="fw-bold mb-0"><?= sanitize($provider['name']) ?></h3>
                        <span style="font-size:1.2rem;opacity:.8"><?= sanitize($provider['name_bn']) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <div class="dropdown">
                    <button class="btn btn-light btn-sm rounded-pill dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-exchange-alt me-1"></i>পরিবর্তন করুন
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="max-height:300px;overflow-y:auto">
                        <?php foreach ($providers as $p): ?>
                        <li>
                            <a class="dropdown-item <?= $p['slug']===$provider['slug']?'active':'' ?>"
                               href="/mfs-portal.php?provider=<?= urlencode($p['slug']) ?>">
                                <?= $p['icon'] ?> <?= sanitize($p['name']) ?>
                                <small class="text-muted ms-1"><?= sanitize($p['name_bn']) ?></small>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Action Panel -->
        <div class="col-lg-7">
            <div class="action-card">
                <!-- Action Tabs -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <?php foreach ($allowedActions as $act): ?>
                    <button type="button"
                            class="btn portal-action-btn rounded-pill <?= $act===$defaultAction ? 'btn-primary' : 'btn-outline-secondary' ?>"
                            data-action="<?= $act ?>">
                        <?= $typeIcons[$act] ?> <?= $typeLabels[$act] ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Transaction Form -->
                <form id="transactionForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="mfs_provider" value="<?= sanitize($provider ? $provider['slug'] : '') ?>">
                    <input type="hidden" name="type" id="actionType" value="<?= $defaultAction ?>">

                    <div id="recipientGroup" <?= $defaultAction==='balance'?'style="display:none"':'' ?>>
                        <div class="mb-3">
                            <label class="form-label fw-bold" id="recipientLabel">
                                <?php echo $defaultAction === 'payment' ? 'মার্চেন্ট নম্বর / ID' : ($defaultAction === 'cashout' ? 'এজেন্ট নম্বর' : 'প্রাপকের নম্বর') ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">🇧🇩</span>
                                <input type="tel" class="form-control form-control-lg" name="recipient"
                                       placeholder="01XXXXXXXXX" maxlength="20" inputmode="numeric"
                                       id="recipientInput">
                            </div>
                        </div>
                    </div>

                    <div id="amountGroup" <?= $defaultAction==='balance'?'style="display:none"':'' ?>>
                        <div class="mb-4">
                            <label class="form-label fw-bold">পরিমাণ (৳)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold">৳</span>
                                <input type="number" class="form-control" name="amount"
                                       placeholder="০.০০" min="1" max="25000" step="0.01"
                                       data-numeric id="amountInput">
                                <span class="input-group-text text-muted">BDT</span>
                            </div>
                            <!-- Quick amount buttons -->
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ([100,200,500,1000,2000,5000] as $amt): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill quick-amount"
                                        data-amount="<?= $amt ?>">৳<?= number_format($amt) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold" id="submitActionBtn">
                        <?= $typeIcons[$defaultAction] ?> <?= $typeLabels[$defaultAction] ?>
                    </button>
                </form>

                <!-- Result display -->
                <div id="txResult"></div>
            </div>
        </div>

        <!-- Sidebar: Provider List + Recent TX -->
        <div class="col-lg-5">
            <!-- All providers -->
            <div class="action-card mb-3">
                <h6 class="fw-bold mb-3">🏦 সব MFS সার্ভিস</h6>
                <div class="row g-2">
                    <?php foreach ($providers as $p): ?>
                    <div class="col-4">
                        <a href="/mfs-portal.php?provider=<?= urlencode($p['slug']) ?>"
                           class="mfs-card py-2 <?= $provider && $p['slug']===$provider['slug']?'border-2':'border' ?>"
                           style="--card-color:<?= sanitize($p['color']) ?>; <?= $provider && $p['slug']===$provider['slug'] ? 'border-color:'.$p['color'].'!important;background:'.$p['color'].'11' : '' ?>">
                            <span class="mfs-card-icon" style="font-size:1.8rem"><?= $p['icon'] ?></span>
                            <div class="mfs-card-name" style="font-size:.72rem"><?= sanitize($p['name']) ?></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions for this provider -->
    <?php if (!empty($recentTx)): ?>
    <div class="action-card mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                <?= $provider ? $provider['icon'] . ' ' . sanitize($provider['name']) : '' ?> সাম্প্রতিক লেনদেন
            </h5>
            <a href="/transaction.php" class="btn btn-sm btn-outline-primary rounded-pill">সব দেখুন</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover tx-table">
                <thead>
                    <tr><th>ধরন</th><th>পরিমাণ</th><th>প্রাপক</th><th>অবস্থা</th><th>তারিখ</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td><?= ($typeIcons[$tx['type']]??'') . ' ' . sanitize($typeLabels[$tx['type']] ?? $tx['type']) ?></td>
                        <td class="fw-bold">৳<?= number_format((float)$tx['amount'],2) ?></td>
                        <td><?= sanitize($tx['recipient']??'—') ?></td>
                        <td><span class="status-badge <?= $statusCls[$tx['status']]??'' ?>"><?= sanitize($tx['status']) ?></span></td>
                        <td><?= formatDate($tx['created_at'],'d M, h:ia') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Quick amount buttons
document.querySelectorAll('.quick-amount').forEach(btn => {
    btn.addEventListener('click', () => {
        const amt = document.getElementById('amountInput');
        if (amt) amt.value = btn.dataset.amount;
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
