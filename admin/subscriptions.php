<?php
/**
 * Admin Subscriptions Management — MFS Compilemama
 */

$pageTitle = 'Subscriptions';
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Handle extend/cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: /admin/subscriptions.php?msg=csrf');
        exit;
    }
    $subId = (int)($_POST['sub_id'] ?? 0);
    $action = $_POST['action_type'] ?? '';

    if ($subId > 0) {
        if ($action === 'cancel') {
            $stmt = $db->prepare("UPDATE subscriptions SET status='cancelled' WHERE id=?");
            $stmt->bind_param('i', $subId);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'extend') {
            $days = max(1, min(365, (int)($_POST['days'] ?? 30)));
            $stmt = $db->prepare("UPDATE subscriptions SET end_date = DATE_ADD(end_date, INTERVAL ? DAY) WHERE id=?");
            $stmt->bind_param('ii', $days, $subId);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === 'activate') {
            $now = date('Y-m-d');
            $end = date('Y-m-d', strtotime('+' . SUB_DAYS . ' days'));
            $stmt = $db->prepare("UPDATE subscriptions SET status='active', start_date=?, end_date=? WHERE id=?");
            $stmt->bind_param('ssi', $now, $end, $subId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: /admin/subscriptions.php?msg=updated');
    exit;
}

$filter  = trim($_GET['status'] ?? '');
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

$where   = '1=1';
$params  = [];
$types   = '';
if (in_array($filter, ['active','expired','cancelled'], true)) {
    $where   .= ' AND s.status=?';
    $params[] = $filter;
    $types   .= 's';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM subscriptions s WHERE $where");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total      = (int)$countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = (int)ceil($total / $perPage);

$paramsPage = array_merge($params, [$perPage, $offset]);
$typesPage  = $types . 'ii';
$stmt       = $db->prepare(
    "SELECT s.*, u.name, u.phone FROM subscriptions s
     JOIN users u ON u.id=s.user_id
     WHERE $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$subs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$msg = $_GET['msg'] ?? '';
?>

<?php if ($msg === 'updated'): ?>
<div class="alert alert-success alert-auto-dismiss rounded-3">✅ Subscription updated.</div>
<?php endif; ?>

<div class="admin-stat-card border-left-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h5 class="fw-bold mb-0">👑 Subscriptions (<?= number_format($total) ?>)</h5>
        <div class="d-flex gap-2">
            <?php foreach ([''=>'All','active'=>'Active','expired'=>'Expired','cancelled'=>'Cancelled'] as $k=>$v): ?>
            <a href="?status=<?= $k ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline-secondary' ?> rounded-pill">
                <?= $v ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subs as $i => $s): ?>
                <tr>
                    <td class="small text-muted"><?= $offset+$i+1 ?></td>
                    <td class="fw-bold"><?= sanitize($s['name']) ?></td>
                    <td><?= sanitize($s['phone']) ?></td>
                    <td><?= formatDate($s['start_date'],'d M y') ?></td>
                    <td><?= formatDate($s['end_date'],'d M y') ?></td>
                    <td>৳<?= number_format($s['amount'],0) ?></td>
                    <td><?= strtoupper(sanitize($s['payment_method'])) ?></td>
                    <td>
                        <span class="badge <?= $s['status']==='active'?'bg-success':($s['status']==='expired'?'bg-danger':'bg-secondary') ?>">
                            <?= $s['status'] ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- Extend -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="action_type" value="extend">
                                <input type="hidden" name="days" value="30">
                                <button type="submit" class="btn btn-xs btn-sm btn-outline-success rounded-3"
                                        title="Extend 30 days">+30d</button>
                            </form>
                            <!-- Cancel -->
                            <?php if ($s['status'] === 'active'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="action_type" value="cancel">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-3"
                                        onclick="return confirm('Cancel this subscription?')">Cancel</button>
                            </form>
                            <?php else: ?>
                            <!-- Re-activate -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="action_type" value="activate">
                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-3">Activate</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filter) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
