<?php
/**
 * Transaction History — MFS Compilemama
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
$db        = getDB();

// Filters
$filterProvider = trim($_GET['provider'] ?? '');
$filterType     = trim($_GET['type'] ?? '');
$filterDate     = trim($_GET['date'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 15;
$offset         = ($page - 1) * $perPage;

$allowed_types     = ['send','cashout','recharge','payment','balance'];
$allowed_providers = array_column($providers, 'slug');

if (!in_array($filterType, $allowed_types, true))       $filterType = '';
if (!in_array($filterProvider, $allowed_providers, true)) $filterProvider = '';
$safeDate = ($filterDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) ? $filterDate : '';

// Build query
$where   = ["user_id = ?"];
$params  = [$userId];
$types   = 'i';

if ($filterProvider) { $where[] = "mfs_provider = ?"; $params[] = $filterProvider; $types .= 's'; }
if ($filterType)     { $where[] = "type = ?";          $params[] = $filterType;     $types .= 's'; }
if ($safeDate)       { $where[] = "DATE(created_at) = ?"; $params[] = $safeDate;    $types .= 's'; }

$whereClause = implode(' AND ', $where);

// Count
$stmtC = $db->prepare("SELECT COUNT(*) FROM transactions WHERE $whereClause");
$stmtC->bind_param($types, ...$params);
$stmtC->execute();
$totalCount = (int)$stmtC->get_result()->fetch_row()[0];
$stmtC->close();
$totalPages = (int)ceil($totalCount / $perPage);

// Fetch
$paramsPage  = array_merge($params, [$perPage, $offset]);
$typesPage   = $types . 'ii';
$stmt        = $db->prepare("SELECT * FROM transactions WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$typeLabels = ['send'=>'সেন্ড মানি','cashout'=>'ক্যাশ আউট','recharge'=>'রিচার্জ','payment'=>'পেমেন্ট','balance'=>'ব্যালেন্স'];
$typeIcons  = ['send'=>'📤','cashout'=>'🏧','recharge'=>'📱','payment'=>'🏪','balance'=>'💰'];
$statusCls  = ['success'=>'badge-success','pending'=>'badge-pending','failed'=>'badge-failed'];
$provMap    = array_column($providers, null, 'slug');

$pageTitle = 'লেনদেন ইতিহাস';
$bodyClass = 'transaction-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>লেনদেন ইতিহাস</h3>
        <a href="/dashboard.php" class="btn btn-outline-primary rounded-pill btn-sm">
            <i class="fas fa-arrow-left me-1"></i>ড্যাশবোর্ড
        </a>
    </div>

    <!-- Filters -->
    <div class="form-card mb-4">
        <form method="GET" action="/transaction.php" class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label class="form-label fw-bold">MFS সার্ভিস</label>
                <select name="provider" class="form-select">
                    <option value="">সব সার্ভিস</option>
                    <?php foreach ($providers as $p): ?>
                    <option value="<?= sanitize($p['slug']) ?>" <?= $filterProvider===$p['slug']?'selected':'' ?>>
                        <?= $p['icon'] ?> <?= sanitize($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-bold">ধরন</label>
                <select name="type" class="form-select">
                    <option value="">সব ধরন</option>
                    <?php foreach ($typeLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterType===$k?'selected':'' ?>><?= $typeIcons[$k] ?> <?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-bold">তারিখ</label>
                <input type="date" name="date" class="form-control" value="<?= sanitize($safeDate) ?>">
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-primary w-100 rounded-3">
                    <i class="fas fa-filter me-1"></i>ফিল্টার
                </button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted">মোট: <strong><?= number_format($totalCount) ?></strong>টি লেনদেন</span>
            <?php if ($filterProvider || $filterType || $safeDate): ?>
            <a href="/transaction.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fas fa-times me-1"></i>ফিল্টার মুছুন
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($transactions)): ?>
        <div class="text-center py-5">
            <div style="font-size:4rem">📭</div>
            <h5 class="mt-3 text-muted">কোনো লেনদেন পাওয়া যায়নি</h5>
            <a href="/mfs-portal.php" class="btn btn-primary rounded-pill mt-2">
                <i class="fas fa-plus me-1"></i>নতুন লেনদেন করুন
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover tx-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>সার্ভিস</th>
                        <th>ধরন</th>
                        <th>পরিমাণ</th>
                        <th>প্রাপক</th>
                        <th>রেফারেন্স</th>
                        <th>অবস্থা</th>
                        <th>তারিখ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $i => $tx):
                        $prov = $provMap[$tx['mfs_provider']] ?? null;
                    ?>
                    <tr>
                        <td class="text-muted small"><?= ($offset + $i + 1) ?></td>
                        <td>
                            <?php if ($prov): ?>
                            <span class="badge rounded-pill text-white fw-bold"
                                  style="background:<?= sanitize($prov['color']) ?>">
                                <?= $prov['icon'] ?> <?= sanitize($prov['name']) ?>
                            </span>
                            <?php else: ?>
                            <?= sanitize(strtoupper($tx['mfs_provider'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= ($typeIcons[$tx['type']]??'') . ' ' . sanitize($typeLabels[$tx['type']] ?? $tx['type']) ?></td>
                        <td class="fw-bold">৳<?= number_format((float)$tx['amount'], 2) ?></td>
                        <td><?= sanitize($tx['recipient'] ?? '—') ?></td>
                        <td><code class="small"><?= sanitize($tx['reference'] ?? '—') ?></code></td>
                        <td>
                            <span class="status-badge <?= $statusCls[$tx['status']] ?? '' ?>">
                                <?php
                                $sl = ['success'=>'সফল','pending'=>'অপেক্ষমান','failed'=>'ব্যর্থ'];
                                echo sanitize($sl[$tx['status']] ?? $tx['status']);
                                ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= formatDate($tx['created_at'], 'd M y, h:ia') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page-1 ?>&provider=<?= urlencode($filterProvider) ?>&type=<?= urlencode($filterType) ?>&date=<?= urlencode($safeDate) ?>">
                        &laquo;
                    </a>
                </li>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&provider=<?= urlencode($filterProvider) ?>&type=<?= urlencode($filterType) ?>&date=<?= urlencode($safeDate) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page+1 ?>&provider=<?= urlencode($filterProvider) ?>&type=<?= urlencode($filterType) ?>&date=<?= urlencode($safeDate) ?>">
                        &raquo;
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
