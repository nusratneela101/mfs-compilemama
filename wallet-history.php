<?php
/**
 * Wallet — Transaction History
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

$perPage  = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;
$typeFilter = in_array($_GET['type'] ?? '', ['add_money','withdraw','transfer_in','transfer_out','fee']) ? ($_GET['type'] ?? '') : '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to']   ?? '';

// Sanitize dates
if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
if ($dateTo   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = '';

$txData   = getWalletTransactions($userId, $perPage, $offset, $typeFilter, $dateFrom, $dateTo);
$txRows   = $txData['rows'];
$total    = $txData['total'];
$pages    = (int)ceil($total / $perPage);
$wallet   = getOrCreateWallet($userId);

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wallet-history-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM for UTF-8
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['রেফারেন্স','ধরন','পরিমাণ','চার্জ','MFS','নম্বর','প্রাপক','ব্যালেন্স আগে','ব্যালেন্স পরে','অবস্থা','তারিখ']);
    $allData = getWalletTransactions($userId, 10000, 0, $typeFilter, $dateFrom, $dateTo);
    foreach ($allData['rows'] as $tx) {
        fputcsv($out, [
            $tx['reference_id'],
            walletTypeLabel($tx['type']),
            $tx['amount'],
            $tx['fee'],
            $tx['mfs_provider'] ?? '',
            $tx['mfs_account']  ?? '',
            $tx['recipient_name'] ?? '',
            $tx['balance_before'],
            $tx['balance_after'],
            $tx['status'],
            $tx['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'ওয়ালেট ইতিহাস';
$bodyClass = 'wallet-page';
include __DIR__ . '/includes/header.php';

function buildQuery(array $overrides = []): string {
    $params = array_merge([
        'type'      => $_GET['type']      ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
        'page'      => $_GET['page']      ?? 1,
    ], $overrides);
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}
?>

<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="/wallet.php" class="btn btn-outline-secondary rounded-circle p-2" style="width:40px;height:40px;line-height:1">
                ←
            </a>
            <div>
                <h4 class="fw-bold mb-0">📋 ওয়ালেট ইতিহাস</h4>
                <small class="text-muted">মোট <?= number_format($total) ?> লেনদেন</small>
            </div>
        </div>
        <a href="<?= buildQuery(['export' => 'csv', 'page' => '']) ?>" class="btn btn-outline-success rounded-pill">
            ⬇️ CSV ডাউনলোড
        </a>
    </div>

    <!-- Filters -->
    <div class="stat-card mb-4">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label class="form-label small fw-semibold">ধরন</label>
                <select name="type" class="form-select rounded-3">
                    <option value="">সব ধরন</option>
                    <?php foreach (['add_money','withdraw','transfer_in','transfer_out','fee'] as $t): ?>
                    <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>>
                        <?= walletTypeIcon($t) ?> <?= walletTypeLabel($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label small fw-semibold">শুরুর তারিখ</label>
                <input type="date" name="date_from" class="form-control rounded-3"
                       value="<?= sanitize($dateFrom) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label small fw-semibold">শেষ তারিখ</label>
                <input type="date" name="date_to" class="form-control rounded-3"
                       value="<?= sanitize($dateTo) ?>">
            </div>
            <div class="col-sm-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill flex-fill">🔍 ফিল্টার</button>
                <a href="/wallet-history.php" class="btn btn-outline-secondary rounded-pill">↺</a>
            </div>
        </form>
    </div>

    <!-- Transaction Table -->
    <div class="stat-card">
        <?php if (empty($txRows)): ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:3rem">📭</div>
            <p class="mt-2">কোনো লেনদেন পাওয়া যায়নি।</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover tx-table mb-0">
                <thead>
                    <tr>
                        <th>ধরন</th>
                        <th>পরিমাণ</th>
                        <th>চার্জ</th>
                        <th>MFS / প্রাপক</th>
                        <th>রেফারেন্স</th>
                        <th>ব্যালেন্স পরে</th>
                        <th>তারিখ</th>
                        <th>অবস্থা</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($txRows as $tx): ?>
                    <tr>
                        <td>
                            <span class="me-1"><?= walletTypeIcon($tx['type']) ?></span>
                            <?= walletTypeLabel($tx['type']) ?>
                        </td>
                        <td class="fw-bold <?= in_array($tx['type'], ['add_money','transfer_in']) ? 'text-success' : 'text-danger' ?>">
                            <?= in_array($tx['type'], ['add_money','transfer_in']) ? '+' : '-' ?>৳<?= number_format((float)$tx['amount'], 2) ?>
                        </td>
                        <td class="small text-muted">
                            <?= $tx['fee'] > 0 ? '৳' . number_format((float)$tx['fee'], 2) : '—' ?>
                        </td>
                        <td class="small">
                            <?php if ($tx['mfs_provider']): ?>
                                <span class="fw-semibold"><?= sanitize($tx['mfs_provider']) ?></span>
                                <?php if ($tx['mfs_account']): ?><br><?= sanitize($tx['mfs_account']) ?><?php endif; ?>
                            <?php elseif ($tx['recipient_name']): ?>
                                <?= sanitize($tx['recipient_name']) ?><br>
                                <span class="text-muted"><?= sanitize($tx['recipient_phone'] ?? '') ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="small font-monospace text-muted"><?= sanitize($tx['reference_id']) ?></td>
                        <td>৳<?= number_format((float)$tx['balance_after'], 2) ?></td>
                        <td class="small text-muted"><?= formatDate($tx['created_at'], 'd M Y, h:ia') ?></td>
                        <td>
                            <span class="status-badge <?= $tx['status'] === 'completed' ? 'badge-success' : 'badge-pending' ?>">
                                <?= sanitize($tx['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination pagination-sm justify-content-center flex-wrap gap-1 mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link rounded-pill" href="<?= buildQuery(['page' => $page - 1]) ?>">‹</a>
                </li>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link rounded-pill" href="<?= buildQuery(['page' => $i]) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                <li class="page-item">
                    <a class="page-link rounded-pill" href="<?= buildQuery(['page' => $page + 1]) ?>">›</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
