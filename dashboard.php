<?php
/**
 * Dashboard — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/subscription.php';

startSecureSession();
requireSubscription();

$user     = getCurrentUser();
$userId   = (int)$_SESSION['user_id'];
$daysLeft = getSubscriptionDaysLeft($userId);
$providers = getMFSProviders();

// Recent transactions
$db    = getDB();
$stmt  = $db->prepare(
    "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentTx = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(amount) as sum FROM transactions WHERE user_id = ? AND status='success'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$txStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle = 'ড্যাশবোর্ড';
$bodyClass = 'dashboard-page';
include __DIR__ . '/includes/header.php';

$typeLabels = ['send'=>'সেন্ড মানি','cashout'=>'ক্যাশ আউট','recharge'=>'রিচার্জ','payment'=>'পেমেন্ট','balance'=>'ব্যালেন্স'];
$typeIcons  = ['send'=>'📤','cashout'=>'🏧','recharge'=>'📱','payment'=>'🏪','balance'=>'💰'];
$statusCls  = ['success'=>'badge-success','pending'=>'badge-pending','failed'=>'badge-failed'];
?>

<div class="dashboard-page">
<div class="container py-4">

    <!-- Welcome Banner -->
    <div class="dash-welcome rounded-4 mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h4 class="fw-bold mb-1">
                    আস-সালামু আলাইকুম, <?= sanitize($user['name'] ?? 'ব্যবহারকারী') ?>! 👋
                </h4>
                <p class="mb-0 opacity-75">
                    স্বাগতম <?= SITE_NAME ?> তে। আজকে কী করবেন?
                </p>
            </div>
            <div class="col-auto d-none d-md-block">
                <div class="text-center">
                    <div style="font-size:2rem;font-weight:700;line-height:1"><?= $daysLeft ?></div>
                    <small class="opacity-75">দিন বাকি</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary bg-opacity-10">💰</div>
                <div class="stat-value"><?= $daysLeft ?></div>
                <div class="stat-label">সাব. দিন বাকি</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f5e9">📊</div>
                <div class="stat-value"><?= number_format((int)($txStats['total'] ?? 0)) ?></div>
                <div class="stat-label">মোট লেনদেন</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd">💸</div>
                <div class="stat-value">৳<?= number_format((float)($txStats['sum'] ?? 0)) ?></div>
                <div class="stat-label">মোট পরিমাণ</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ef">🏦</div>
                <div class="stat-value"><?= count($providers) ?></div>
                <div class="stat-label">MFS সার্ভিস</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- MFS Provider Grid -->
        <div class="col-lg-8">
            <div class="stat-card">
                <h5 class="fw-bold mb-4">🏦 MFS সার্ভিস বেছে নিন</h5>
                <div class="row g-2">
                    <?php foreach ($providers as $p): ?>
                    <div class="col-4 col-sm-3 col-md-2">
                        <a href="/mfs-portal.php?provider=<?= urlencode($p['slug']) ?>"
                           class="mfs-card py-3"
                           style="--card-color: <?= sanitize($p['color']) ?>">
                            <span class="mfs-card-icon" style="font-size:2rem"><?= $p['icon'] ?></span>
                            <div class="mfs-card-name" style="font-size:.78rem"><?= sanitize($p['name']) ?></div>
                            <div class="mfs-card-name-bn" style="font-size:.72rem"><?= sanitize($p['name_bn']) ?></div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="stat-card mb-3">
                <h5 class="fw-bold mb-3">⚡ দ্রুত কার্যক্রম</h5>
                <div class="d-grid gap-2">
                    <a href="/mfs-portal.php?action=send" class="btn btn-outline-primary rounded-3 text-start">
                        📤 সেন্ড মানি
                    </a>
                    <a href="/mfs-portal.php?action=cashout" class="btn btn-outline-primary rounded-3 text-start">
                        🏧 ক্যাশ আউট
                    </a>
                    <a href="/mfs-portal.php?action=recharge" class="btn btn-outline-primary rounded-3 text-start">
                        📱 মোবাইল রিচার্জ
                    </a>
                    <a href="/mfs-portal.php?action=payment" class="btn btn-outline-primary rounded-3 text-start">
                        🏪 পেমেন্ট
                    </a>
                    <a href="/transaction.php" class="btn btn-outline-secondary rounded-3 text-start">
                        📋 সব লেনদেন দেখুন
                    </a>
                </div>
            </div>

            <!-- Subscription Info -->
            <div class="stat-card">
                <h5 class="fw-bold mb-3">👑 সাবস্ক্রিপশন</h5>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="text-center">
                        <div class="text-primary fw-bold" style="font-size:2.5rem;line-height:1"><?= $daysLeft ?></div>
                        <small class="text-muted">দিন বাকি</small>
                    </div>
                    <div>
                        <div class="badge bg-success mb-1">✅ সক্রিয়</div>
                        <div class="small text-muted">
                            <?php
                            $sub = getSubscriptionDetails($userId);
                            if ($sub) echo 'মেয়াদ: ' . formatDate($sub['end_date'], 'd M Y');
                            ?>
                        </div>
                    </div>
                </div>
                <a href="/subscribe.php" class="btn btn-outline-primary btn-sm rounded-pill w-100">
                    🔄 নবায়ন করুন
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <?php if (!empty($recentTx)): ?>
    <div class="stat-card mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">📋 সাম্প্রতিক লেনদেন</h5>
            <a href="/transaction.php" class="btn btn-sm btn-outline-primary rounded-pill">সব দেখুন</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover tx-table">
                <thead>
                    <tr>
                        <th>সার্ভিস</th>
                        <th>ধরন</th>
                        <th>পরিমাণ</th>
                        <th>প্রাপক</th>
                        <th>অবস্থা</th>
                        <th>তারিখ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td class="fw-bold"><?= sanitize(strtoupper($tx['mfs_provider'])) ?></td>
                        <td><?= ($typeIcons[$tx['type']] ?? '') . ' ' . sanitize($typeLabels[$tx['type']] ?? $tx['type']) ?></td>
                        <td class="fw-bold">৳<?= number_format((float)$tx['amount'], 2) ?></td>
                        <td><?= sanitize($tx['recipient'] ?? '—') ?></td>
                        <td>
                            <span class="status-badge <?= $statusCls[$tx['status']] ?? '' ?>">
                                <?= sanitize($tx['status']) ?>
                            </span>
                        </td>
                        <td><?= formatDate($tx['created_at'], 'd M, h:ia') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
