<?php
/**
 * Admin Payments Management — MFS Compilemama
 */

$pageTitle = 'Payments';
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../includes/subscription.php';

$db = getDB();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: /admin/payments.php?msg=csrf');
        exit;
    }
    $payId  = (int)($_POST['payment_id'] ?? 0);
    $action = $_POST['action_type'] ?? '';

    if ($payId > 0) {
        // Get payment info
        $stmtP = $db->prepare("SELECT * FROM payments WHERE id=? LIMIT 1");
        $stmtP->bind_param('i', $payId);
        $stmtP->execute();
        $pay = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();

        if ($pay && $action === 'approve') {
            // Update payment status
            $stmt = $db->prepare("UPDATE payments SET status='completed', approved_by=? WHERE id=?");
            $adminId = (int)$_SESSION['admin_id'];
            $stmt->bind_param('ii', $adminId, $payId);
            $stmt->execute();
            $stmt->close();
            // Activate subscription
            activateSubscription((int)$pay['user_id'], $pay['payment_method'], (float)$pay['amount']);
        } elseif ($pay && $action === 'reject') {
            $stmt = $db->prepare("UPDATE payments SET status='failed' WHERE id=?");
            $stmt->bind_param('i', $payId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: /admin/payments.php?msg=updated');
    exit;
}

$filter  = trim($_GET['status'] ?? 'pending');
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

$allowed = ['pending','completed','failed',''];
if (!in_array($filter, $allowed, true)) $filter = 'pending';

$where  = '1=1';
$params = [];
$types  = '';
if ($filter !== '') {
    $where   .= ' AND p.status=?';
    $params[] = $filter;
    $types   .= 's';
}

$cntStmt = $db->prepare("SELECT COUNT(*) FROM payments p WHERE $where");
if ($types) $cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$total = (int)$cntStmt->get_result()->fetch_row()[0];
$cntStmt->close();
$totalPages = (int)ceil($total / $perPage);

$paramsPage = array_merge($params, [$perPage, $offset]);
$typesPage  = $types . 'ii';
$stmt       = $db->prepare(
    "SELECT p.*, u.name, u.phone FROM payments p
     JOIN users u ON u.id=p.user_id
     WHERE $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary
$summary = $db->query(
    "SELECT status, COUNT(*) as cnt, SUM(amount) as total FROM payments GROUP BY status"
)->fetch_all(MYSQLI_ASSOC);
$summaryMap = [];
foreach ($summary as $s) $summaryMap[$s['status']] = $s;

$msg = $_GET['msg'] ?? '';
?>

<?php if ($msg === 'updated'): ?>
<div class="alert alert-success alert-auto-dismiss rounded-3">✅ Payment status updated.</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['key'=>'pending',   'label'=>'Pending',   'color'=>'#fdcb6e','bg'=>'#fffbec','icon'=>'fas fa-clock'],
        ['key'=>'completed', 'label'=>'Completed', 'color'=>'#00b894','bg'=>'#e8f8f5','icon'=>'fas fa-check-circle'],
        ['key'=>'failed',    'label'=>'Failed',    'color'=>'#d63031','bg'=>'#fdecea','icon'=>'fas fa-times-circle'],
    ];
    foreach ($cards as $c):
        $data = $summaryMap[$c['key']] ?? ['cnt'=>0,'total'=>0];
    ?>
    <div class="col-md-4">
        <div class="admin-stat-card" style="border-left-color:<?= $c['color'] ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold"><?= $c['label'] ?> Payments</div>
                    <div class="fw-bold fs-4"><?= number_format($data['cnt']) ?></div>
                    <div class="small text-muted">৳<?= number_format((float)$data['total'],0) ?></div>
                </div>
                <div style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem">
                    <i class="<?= $c['icon'] ?>"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-stat-card border-left-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h5 class="fw-bold mb-0">💳 Payments (<?= number_format($total) ?>)</h5>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['pending'=>'Pending','completed'=>'Completed','failed'=>'Failed',''=>'All'] as $k=>$v): ?>
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
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transaction ID</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                    <td class="small text-muted"><?= $offset+$i+1 ?></td>
                    <td class="fw-bold"><?= sanitize($p['name']) ?></td>
                    <td><?= sanitize($p['phone']) ?></td>
                    <td class="fw-bold text-success">৳<?= number_format($p['amount'],0) ?></td>
                    <td><span class="badge bg-primary"><?= strtoupper(sanitize($p['payment_method'])) ?></span></td>
                    <td><code class="small"><?= sanitize($p['transaction_id']) ?></code></td>
                    <td>
                        <span class="badge <?= $p['status']==='completed'?'bg-success':($p['status']==='failed'?'bg-danger':'bg-warning') ?>">
                            <?= $p['status'] ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= formatDate($p['created_at'],'d M y, h:ia') ?></td>
                    <td>
                        <?php if ($p['status'] === 'pending'): ?>
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action_type" value="approve">
                                <button type="submit" class="btn btn-sm btn-success rounded-3"
                                        onclick="return confirm('Approve this payment and activate subscription?')">
                                    ✅ Approve
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="action_type" value="reject">
                                <button type="submit" class="btn btn-sm btn-danger rounded-3"
                                        onclick="return confirm('Reject this payment?')">
                                    ❌ Reject
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
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
