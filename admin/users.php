<?php
/**
 * Admin Users Management — MFS Compilemama
 */

$pageTitle = 'Users';
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../includes/email.php';

$db = getDB();

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Toast_error_redirect('/admin/users.php');
    }
    $uid    = (int)($_POST['user_id'] ?? 0);
    $newSt  = $_POST['new_status'] ?? '';
    $allowed = ['active','inactive','blocked'];
    if ($uid > 0 && in_array($newSt, $allowed, true)) {
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newSt, $uid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: /admin/users.php?msg=updated');
    exit;
}

// Handle admin resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verify'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Toast_error_redirect('/admin/users.php');
    }
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        $stmt = $db->prepare("SELECT id, name, email, status FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $targetUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($targetUser && !empty($targetUser['email']) && $targetUser['status'] === 'inactive') {
            $verifyToken = bin2hex(random_bytes(32));
            $expiresAt   = date('Y-m-d H:i:s', time() + VERIFICATION_EXPIRY_HOURS * 3600);
            $stmtV = $db->prepare(
                "INSERT INTO email_verifications (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)"
            );
            $stmtV->bind_param('isss', $uid, $targetUser['email'], $verifyToken, $expiresAt);
            $stmtV->execute();
            $stmtV->close();
            sendVerificationEmail($targetUser['email'], $targetUser['name'], $verifyToken);
        }
    }
    header('Location: /admin/users.php?msg=email_sent');
    exit;
}

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$types  = '';

if ($search) {
    $where    .= " AND (u.phone LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $like      = '%' . $search . '%';
    $params[]  = $like;
    $params[]  = $like;
    $params[]  = $like;
    $types    .= 'sss';
}

$stmtC = $db->prepare("SELECT COUNT(*) FROM users u WHERE $where");
if ($types) $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$total = (int)$stmtC->get_result()->fetch_row()[0];
$stmtC->close();
$totalPages = (int)ceil($total / $perPage);

$paramsPage = array_merge($params, [$perPage, $offset]);
$typesPage  = $types . 'ii';
$stmt       = $db->prepare(
    "SELECT u.*,
        (SELECT COUNT(*) FROM subscriptions s WHERE s.user_id=u.id AND s.status='active' AND s.end_date>=CURDATE()) as has_sub,
        (SELECT COUNT(*) FROM email_verifications ev WHERE ev.user_id=u.id AND ev.used=1 LIMIT 1) as email_verified
     FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$msg = $_GET['msg'] ?? '';
?>

<?php if ($msg === 'updated'): ?>
<div class="alert alert-success alert-auto-dismiss rounded-3">✅ User status updated.</div>
<?php elseif ($msg === 'email_sent'): ?>
<div class="alert alert-success alert-auto-dismiss rounded-3">✅ Verification email sent.</div>
<?php endif; ?>

<div class="admin-stat-card border-left-0">
    <!-- Search -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h5 class="fw-bold mb-0">👥 All Users (<?= number_format($total) ?>)</h5>
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="search" class="form-control" placeholder="Search by phone, name or email..."
                   value="<?= sanitize($search) ?>" style="max-width:280px">
            <button type="submit" class="btn btn-primary rounded-3">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
            <a href="/admin/users.php" class="btn btn-outline-secondary rounded-3">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Email Verified</th>
                    <th>Sub</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                    <td class="fw-bold"><?= sanitize($u['name']) ?></td>
                    <td><?= sanitize($u['phone']) ?></td>
                    <td class="small"><?= sanitize($u['email'] ?? '—') ?></td>
                    <td>
                        <?php if ($u['email_verified']): ?>
                        <span class="badge bg-success">✅ Verified</span>
                        <?php elseif (!empty($u['email'])): ?>
                        <span class="badge bg-warning text-dark">⏳ Pending</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">No Email</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['has_sub']): ?>
                        <span class="badge bg-success">✅ Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">No Sub</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $u['status']==='active'?'bg-success':($u['status']==='blocked'?'bg-danger':'bg-warning') ?>">
                            <?= $u['status'] ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= formatDate($u['created_at'], 'd M y') ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary rounded-3 dropdown-toggle" data-bs-toggle="dropdown">
                                Action
                            </button>
                            <ul class="dropdown-menu">
                                <?php if ($u['status'] !== 'active'): ?>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="dropdown-item text-success">✅ Activate</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'blocked'): ?>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="blocked">
                                        <button type="submit" class="dropdown-item text-danger"
                                                onclick="return confirm('Block this user?')">🚫 Block</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'inactive'): ?>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="inactive">
                                        <button type="submit" class="dropdown-item text-warning">⏸ Deactivate</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] === 'inactive' && !empty($u['email'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="resend_verify" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="dropdown-item text-info">
                                            📧 Resend Verification Email
                                        </button>
                                    </form>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>

$db = getDB();

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        Toast_error_redirect('/admin/users.php');
    }
    $uid    = (int)($_POST['user_id'] ?? 0);
    $newSt  = $_POST['new_status'] ?? '';
    $allowed = ['active','inactive','blocked'];
    if ($uid > 0 && in_array($newSt, $allowed, true)) {
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newSt, $uid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: /admin/users.php?msg=updated');
    exit;
}

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$types  = '';

if ($search) {
    $where    .= " AND (phone LIKE ? OR name LIKE ?)";
    $like      = '%' . $search . '%';
    $params[]  = $like;
    $params[]  = $like;
    $types    .= 'ss';
}

$stmtC = $db->prepare("SELECT COUNT(*) FROM users WHERE $where");
if ($types) $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$total = (int)$stmtC->get_result()->fetch_row()[0];
$stmtC->close();
$totalPages = (int)ceil($total / $perPage);

$paramsPage = array_merge($params, [$perPage, $offset]);
$typesPage  = $types . 'ii';
$stmt       = $db->prepare("SELECT u.*, (SELECT COUNT(*) FROM subscriptions s WHERE s.user_id=u.id AND s.status='active' AND s.end_date>=CURDATE()) as has_sub FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$msg = $_GET['msg'] ?? '';
?>

<?php if ($msg === 'updated'): ?>
<div class="alert alert-success alert-auto-dismiss rounded-3">✅ User status updated.</div>
<?php endif; ?>

<div class="admin-stat-card border-left-0">
    <!-- Search -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h5 class="fw-bold mb-0">👥 All Users (<?= number_format($total) ?>)</h5>
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="search" class="form-control" placeholder="Search by phone or name..."
                   value="<?= sanitize($search) ?>" style="max-width:250px">
            <button type="submit" class="btn btn-primary rounded-3">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
            <a href="/admin/users.php" class="btn btn-outline-secondary rounded-3">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Sub</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                    <td class="fw-bold"><?= sanitize($u['name']) ?></td>
                    <td><?= sanitize($u['phone']) ?></td>
                    <td class="small"><?= sanitize($u['email'] ?? '—') ?></td>
                    <td>
                        <?php if ($u['has_sub']): ?>
                        <span class="badge bg-success">✅ Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">No Sub</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $u['status']==='active'?'bg-success':($u['status']==='blocked'?'bg-danger':'bg-warning') ?>">
                            <?= $u['status'] ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= formatDate($u['created_at'], 'd M y') ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary rounded-3 dropdown-toggle" data-bs-toggle="dropdown">
                                Action
                            </button>
                            <ul class="dropdown-menu">
                                <?php if ($u['status'] !== 'active'): ?>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="dropdown-item text-success">✅ Activate</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'blocked'): ?>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="blocked">
                                        <button type="submit" class="dropdown-item text-danger"
                                                onclick="return confirm('Block this user?')">🚫 Block</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <?php if ($u['status'] !== 'inactive'): ?>
                                <li>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="inactive">
                                        <button type="submit" class="dropdown-item text-warning">⏸ Deactivate</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
