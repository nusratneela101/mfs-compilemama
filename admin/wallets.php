<?php
/**
 * Admin — Wallet Management
 * MFS Compilemama
 */

$pageTitle = 'Wallet Management';
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Handle freeze/unfreeze
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf     = $_POST['csrf_token'] ?? '';
    $walletId = (int)($_POST['wallet_id'] ?? 0);
    $action   = $_POST['wallet_action'] ?? '';

    if (verifyCSRFToken($csrf) && $walletId && in_array($action, ['freeze','unfreeze','close'])) {
        $newStatus = match($action) {
            'freeze'   => 'frozen',
            'unfreeze' => 'active',
            'close'    => 'closed',
        };
        $stmt = $db->prepare("UPDATE wallets SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $walletId);
        $stmt->execute();
        $stmt->close();
        $msg = 'ওয়ালেট স্ট্যাটাস আপডেট হয়েছে: ' . $newStatus;
    }
}

// Platform-wide stats
$stats = $db->query(
    "SELECT
        COUNT(*)                   AS total_wallets,
        SUM(balance)               AS total_balance,
        SUM(total_added)           AS total_added,
        SUM(total_withdrawn)       AS total_withdrawn,
        SUM(total_transferred)     AS total_transferred,
        SUM(total_fees)            AS total_fees
     FROM wallets"
)->fetch_assoc();

// Filters
$search = trim($_GET['search'] ?? '');
$status = in_array($_GET['status'] ?? '', ['active','frozen','closed']) ? ($_GET['status'] ?? '') : '';
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$whereClause = 'WHERE 1=1';
$params      = [];
$types       = '';

if ($search) {
    $whereClause .= ' AND (u.phone LIKE ? OR u.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($status) {
    $whereClause .= ' AND w.status = ?';
    $params[]     = $status;
    $types       .= 's';
}

// Count
$countSql  = "SELECT COUNT(*) FROM wallets w LEFT JOIN users u ON u.id = w.user_id $whereClause";
$stmtCount = $db->prepare($countSql);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$total = (int)$stmtCount->get_result()->fetch_row()[0];
$stmtCount->close();
$pages = (int)ceil($total / $perPage);

// Rows
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';
$rowSql   = "SELECT w.*, u.name, u.phone, u.email
             FROM wallets w
             LEFT JOIN users u ON u.id = w.user_id
             $whereClause
             ORDER BY w.created_at DESC
             LIMIT ? OFFSET ?";
$stmtRows = $db->prepare($rowSql);
$stmtRows->bind_param($types, ...$params);
$stmtRows->execute();
$wallets = $stmtRows->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRows->close();

// Individual wallet transactions (for modal)
$viewTx = [];
if (isset($_GET['view_wallet'])) {
    $wid = (int)$_GET['view_wallet'];
    $res = $db->prepare(
        "SELECT wt.*, u.name AS recipient_name
         FROM wallet_transactions wt
         LEFT JOIN users u ON u.id = wt.recipient_user_id
         WHERE wt.wallet_id = ?
         ORDER BY wt.created_at DESC
         LIMIT 50"
    );
    $res->bind_param('i', $wid);
    $res->execute();
    $viewTx = $res->get_result()->fetch_all(MYSQLI_ASSOC);
    $res->close();
}

function walletTypeLabelAdmin(string $type): string {
    $labels = ['add_money'=>'Add Money','withdraw'=>'Withdraw','transfer_in'=>'Transfer In','transfer_out'=>'Transfer Out','fee'=>'Fee'];
    return $labels[$type] ?? $type;
}
?>

<?php if (!empty($msg)): ?>
<div class="alert alert-success rounded-3"><?= sanitize($msg) ?></div>
<?php endif; ?>

<!-- Platform Stats -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['label'=>'Total Wallets',       'value'=> number_format((int)$stats['total_wallets']),                     'icon'=>'💰','bg'=>'#fce4ef'],
        ['label'=>'Total Platform Balance','value'=>'৳'.number_format((float)$stats['total_balance'],2),            'icon'=>'💹','bg'=>'#e8f5e9'],
        ['label'=>'Total Deposited',     'value'=>'৳'.number_format((float)$stats['total_added'],0),                'icon'=>'📥','bg'=>'#e3f2fd'],
        ['label'=>'Total Withdrawn',     'value'=>'৳'.number_format((float)$stats['total_withdrawn'],0),            'icon'=>'📤','bg'=>'#fff3e0'],
        ['label'=>'Total Transferred',   'value'=>'৳'.number_format((float)$stats['total_transferred'],0),          'icon'=>'🔄','bg'=>'#f3e5f5'],
        ['label'=>'Fee Revenue',         'value'=>'৳'.number_format((float)$stats['total_fees'],2),                 'icon'=>'💸','bg'=>'#e0f7fa'],
    ];
    foreach ($statCards as $sc): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card text-center p-3">
            <div class="stat-icon mx-auto mb-2" style="background:<?= $sc['bg'] ?>"><?= $sc['icon'] ?></div>
            <div class="stat-value" style="font-size:1rem"><?= $sc['value'] ?></div>
            <div class="stat-label" style="font-size:.7rem"><?= $sc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control rounded-3"
                       placeholder="Search name / phone..." value="<?= sanitize($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select rounded-3">
                    <option value="">All Status</option>
                    <?php foreach (['active','frozen','closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-3">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/wallets.php" class="btn btn-outline-secondary w-100 rounded-3">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Wallets Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold">Wallets (<?= number_format($total) ?>)</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Balance</th>
                    <th>Added</th>
                    <th>Withdrawn</th>
                    <th>Transferred</th>
                    <th>Fees</th>
                    <th>Free Used</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wallets)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No wallets found.</td></tr>
                <?php endif; ?>
                <?php foreach ($wallets as $w): ?>
                <tr>
                    <td class="small text-muted"><?= $w['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= sanitize($w['name'] ?? '—') ?></div>
                        <div class="small text-muted"><?= sanitize($w['phone'] ?? '—') ?></div>
                    </td>
                    <td class="fw-bold text-primary">৳<?= number_format((float)$w['balance'], 2) ?></td>
                    <td class="small">৳<?= number_format((float)$w['total_added'], 0) ?></td>
                    <td class="small">৳<?= number_format((float)$w['total_withdrawn'], 0) ?></td>
                    <td class="small">৳<?= number_format((float)$w['total_transferred'], 0) ?></td>
                    <td class="small text-danger">৳<?= number_format((float)$w['total_fees'], 2) ?></td>
                    <td class="small">
                        <?= number_format((float)$w['free_limit_used'], 0) ?> / 10,000
                        <div class="progress mt-1" style="height:4px">
                            <div class="progress-bar bg-primary" style="width:<?= min(100, (float)$w['free_limit_used'] / 100) ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $w['status'] === 'active' ? 'bg-success' : ($w['status'] === 'frozen' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                            <?= ucfirst($w['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="?view_wallet=<?= $w['id'] ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
                           class="btn btn-sm btn-outline-primary rounded-pill me-1">History</a>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="wallet_id" value="<?= $w['id'] ?>">
                            <?php if ($w['status'] === 'active'): ?>
                            <button name="wallet_action" value="freeze"
                                    class="btn btn-sm btn-outline-warning rounded-pill"
                                    onclick="return confirm('Freeze this wallet?')">Freeze</button>
                            <?php elseif ($w['status'] === 'frozen'): ?>
                            <button name="wallet_action" value="unfreeze"
                                    class="btn btn-sm btn-outline-success rounded-pill"
                                    onclick="return confirm('Unfreeze this wallet?')">Unfreeze</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center gap-1">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link rounded-pill"
                       href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Wallet Transaction History (shown when view_wallet is set) -->
<?php if (!empty($viewTx) && isset($_GET['view_wallet'])): ?>
<div class="card border-0 shadow-sm rounded-3 mt-4">
    <div class="card-header bg-white d-flex justify-content-between py-3">
        <h6 class="mb-0 fw-bold">Transaction History — Wallet #<?= (int)$_GET['view_wallet'] ?></h6>
        <a href="/admin/wallets.php?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"
           class="btn btn-sm btn-outline-secondary rounded-pill">Close</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Type</th><th>Amount</th><th>Fee</th><th>MFS</th>
                    <th>Recipient</th><th>Reference</th><th>Balance After</th><th>Status</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viewTx as $tx): ?>
                <tr>
                    <td><?= walletTypeLabelAdmin($tx['type']) ?></td>
                    <td class="fw-bold <?= in_array($tx['type'],['add_money','transfer_in']) ? 'text-success' : 'text-danger' ?>">
                        ৳<?= number_format((float)$tx['amount'], 2) ?>
                    </td>
                    <td><?= $tx['fee'] > 0 ? '৳'.number_format((float)$tx['fee'],2) : '—' ?></td>
                    <td><?= sanitize($tx['mfs_provider'] ?? '—') ?></td>
                    <td><?= sanitize($tx['recipient_name'] ?? '—') ?></td>
                    <td class="font-monospace"><?= sanitize($tx['reference_id']) ?></td>
                    <td>৳<?= number_format((float)$tx['balance_after'], 2) ?></td>
                    <td><span class="badge <?= $tx['status']==='completed' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $tx['status'] ?></span></td>
                    <td><?= formatDate($tx['created_at'],'d M Y, h:ia') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
