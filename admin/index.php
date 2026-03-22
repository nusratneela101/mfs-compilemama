<?php
/**
 * Admin Dashboard — MFS Compilemama
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/admin_header.php';

$db = getDB();

// Stats
$stats = [];
$stats['users']         = (int)$db->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$stats['active_subs']   = (int)$db->query("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND end_date >= CURDATE()")->fetch_row()[0];
$stats['pending_pay']   = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetch_row()[0];
$stats['total_revenue'] = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetch_row()[0];

// Recent users
$recentUsers = $db->query(
    "SELECT id, name, phone, status, created_at FROM users ORDER BY created_at DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// Recent transactions
$recentTx = $db->query(
    "SELECT t.*, u.name, u.phone FROM transactions t JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// Monthly revenue for chart (last 6 months)
$monthlyRev = $db->query(
    "SELECT DATE_FORMAT(created_at,'%b %Y') as month, SUM(amount) as total
     FROM payments WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY created_at ASC"
)->fetch_all(MYSQLI_ASSOC);

// Subscription types by provider
$subByMethod = $db->query(
    "SELECT payment_method, COUNT(*) as cnt FROM subscriptions GROUP BY payment_method"
)->fetch_all(MYSQLI_ASSOC);
?>

<div class="row g-4 mb-4">
    <?php
    $statItems = [
        ['label'=>'Total Users',         'value'=> number_format($stats['users']),   'icon'=>'fas fa-users',           'color'=>'#E2136E', 'bg'=>'#fce4ef'],
        ['label'=>'Active Subscriptions','value'=> number_format($stats['active_subs']),'icon'=>'fas fa-crown',        'color'=>'#00b894', 'bg'=>'#e8f8f5'],
        ['label'=>'Pending Payments',    'value'=> number_format($stats['pending_pay']),'icon'=>'fas fa-clock',        'color'=>'#fdcb6e', 'bg'=>'#fffbec'],
        ['label'=>'Total Revenue',       'value'=>'৳'.number_format($stats['total_revenue'],0),'icon'=>'fas fa-taka-sign','color'=>'#0984e3','bg'=>'#e3f2fd'],
    ];
    foreach ($statItems as $s): ?>
    <div class="col-6 col-xl-3">
        <div class="admin-stat-card" style="border-left-color:<?= $s['color'] ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small fw-bold mb-1"><?= $s['label'] ?></div>
                    <div class="fw-bold" style="font-size:1.8rem;line-height:1"><?= $s['value'] ?></div>
                </div>
                <div style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem">
                    <i class="<?= $s['icon'] ?>"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="admin-stat-card border-left-0">
            <h5 class="fw-bold mb-4">📈 Monthly Revenue (Last 6 Months)</h5>
            <canvas id="revenueChart" height="100"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-stat-card border-left-0">
            <h5 class="fw-bold mb-4">💳 Payment Methods</h5>
            <canvas id="methodChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Users & Transactions -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-stat-card border-left-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">👥 Recent Users</h5>
                <a href="/admin/users.php" class="btn btn-sm btn-outline-primary rounded-pill">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light"><tr><th>Name</th><th>Phone</th><th>Status</th><th>Joined</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td class="fw-bold"><?= sanitize($u['name']) ?></td>
                            <td><?= sanitize($u['phone']) ?></td>
                            <td>
                                <span class="badge <?= $u['status']==='active'?'bg-success':($u['status']==='blocked'?'bg-danger':'bg-warning') ?>">
                                    <?= $u['status'] ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= formatDate($u['created_at'],'d M y') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-stat-card border-left-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">💸 Recent Transactions</h5>
                <a href="/admin/users.php" class="btn btn-sm btn-outline-primary rounded-pill">View Users</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light"><tr><th>User</th><th>Provider</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentTx as $tx): ?>
                        <tr>
                            <td><?= sanitize($tx['name']) ?></td>
                            <td><span class="badge bg-primary"><?= strtoupper(sanitize($tx['mfs_provider'])) ?></span></td>
                            <td class="fw-bold">৳<?= number_format((float)$tx['amount'],0) ?></td>
                            <td>
                                <span class="badge <?= $tx['status']==='success'?'bg-success':($tx['status']==='failed'?'bg-danger':'bg-warning') ?>">
                                    <?= $tx['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue Chart
const revLabels = <?= json_encode(array_column($monthlyRev, 'month')) ?>;
const revData   = <?= json_encode(array_map(fn($r) => (float)$r['total'], $monthlyRev)) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: revLabels.length ? revLabels : ['No data'],
        datasets: [{
            label: 'Revenue (BDT)',
            data: revData.length ? revData : [0],
            backgroundColor: 'rgba(226,19,110,0.7)',
            borderColor: '#E2136E',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: { responsive:true, plugins:{ legend:{display:false} }, scales:{ y:{beginAtZero:true} } }
});

// Method Chart
const methLabels = <?= json_encode(array_column($subByMethod, 'payment_method')) ?>;
const methData   = <?= json_encode(array_map(fn($r) => (int)$r['cnt'], $subByMethod)) ?>;
const methColors = ['#E2136E','#F16528','#8B1A7C','#00A651','#0066CC','#aaa'];

new Chart(document.getElementById('methodChart'), {
    type: 'doughnut',
    data: {
        labels: methLabels.length ? methLabels : ['None'],
        datasets: [{ data: methData.length ? methData : [1], backgroundColor: methColors }]
    },
    options: { responsive:true, plugins:{ legend:{position:'bottom'} } }
});
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
