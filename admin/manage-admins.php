<?php
/**
 * Admin — Manage Admin Users
 * MFS Compilemama
 */

$pageTitle = 'Manage Admins';
require_once __DIR__ . '/admin_header.php';

$db  = getDB();
$msg = '';
$err = '';

// ── Add Admin ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 50) {
            $err = 'Username must be 3–50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $err = 'Username may only contain letters, numbers and underscores.';
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $err = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            // Check duplicate username
            $chk = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
            $chk->bind_param('s', $username);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $err = 'Username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $ins  = $db->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)");
                $ins->bind_param('sss', $username, $hash, $email);
                $ins->execute();
                $ins->close();
                $msg = 'Admin user added successfully.';
            }
            $chk->close();
        }
    }
}

// ── Edit Admin ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $id       = (int)($_POST['admin_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($id <= 0) {
            $err = 'Invalid admin ID.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $err = 'Username must be 3–50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $err = 'Username may only contain letters, numbers and underscores.';
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Invalid email address.';
        } else {
            // Check duplicate username (exclude current)
            $chk = $db->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
            $chk->bind_param('si', $username, $id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $err = 'Username already in use by another admin.';
            } else {
                $upd = $db->prepare("UPDATE admin_users SET username = ?, email = ? WHERE id = ?");
                $upd->bind_param('ssi', $username, $email, $id);
                $upd->execute();
                $upd->close();
                // Update session username if editing self
                if ($id === (int)$_SESSION['admin_id']) {
                    $_SESSION['admin_username'] = $username;
                }
                $msg = 'Admin user updated.';
            }
            $chk->close();
        }
    }
}

// ── Change Password ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $id      = (int)($_POST['admin_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($id <= 0) {
            $err = 'Invalid admin ID.';
        } elseif (strlen($newPass) < 6) {
            $err = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $upd  = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $upd->bind_param('si', $hash, $id);
            $upd->execute();
            $upd->close();
            $msg = 'Password changed successfully.';
        }
    }
}

// ── Delete Admin ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $id    = (int)($_POST['admin_id'] ?? 0);
        $total = (int)$db->query("SELECT COUNT(*) FROM admin_users")->fetch_row()[0];

        if ($id <= 0) {
            $err = 'Invalid admin ID.';
        } elseif ($id === (int)$_SESSION['admin_id']) {
            $err = 'You cannot delete your own account.';
        } elseif ($total <= 1) {
            $err = 'Cannot delete the last remaining admin.';
        } else {
            $del = $db->prepare("DELETE FROM admin_users WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            $msg = 'Admin user deleted.';
        }
    }
}

// ── Fetch all admins ───────────────────────────────────────────────────────
$admins = $db->query(
    "SELECT id, username, email, last_login, created_at FROM admin_users ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= sanitize($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($err): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Add Admin -->
<div class="admin-stat-card border-left-0 mb-4">
    <h5 class="fw-bold mb-3"><i class="fas fa-user-plus me-2 text-primary"></i>Add New Admin</h5>
    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" maxlength="150">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" minlength="6" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-plus me-1"></i>Add Admin
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Admin List -->
<div class="admin-stat-card border-left-0">
    <h5 class="fw-bold mb-3"><i class="fas fa-users-cog me-2 text-primary"></i>Admin Users (<?= count($admins) ?>)</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $admin): ?>
                <tr>
                    <td><?= (int)$admin['id'] ?></td>
                    <td class="fw-bold">
                        <?= sanitize($admin['username']) ?>
                        <?php if ((int)$admin['id'] === (int)$_SESSION['admin_id']): ?>
                            <span class="badge bg-primary ms-1">You</span>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($admin['email'] ?? '—') ?></td>
                    <td class="text-muted small"><?= $admin['last_login'] ? formatDate($admin['last_login']) : 'Never' ?></td>
                    <td class="text-muted small"><?= formatDate($admin['created_at'], 'd M Y') ?></td>
                    <td>
                        <!-- Edit button -->
                        <button class="btn btn-sm btn-outline-primary me-1"
                                data-bs-toggle="modal" data-bs-target="#editModal"
                                data-id="<?= (int)$admin['id'] ?>"
                                data-username="<?= sanitize($admin['username']) ?>"
                                data-email="<?= sanitize($admin['email'] ?? '') ?>">
                            <i class="fas fa-edit"></i>
                        </button>
                        <!-- Change password button -->
                        <button class="btn btn-sm btn-outline-warning me-1"
                                data-bs-toggle="modal" data-bs-target="#pwdModal"
                                data-id="<?= (int)$admin['id'] ?>"
                                data-username="<?= sanitize($admin['username']) ?>">
                            <i class="fas fa-key"></i>
                        </button>
                        <!-- Delete button (disabled for self or last admin) -->
                        <?php if ((int)$admin['id'] !== (int)$_SESSION['admin_id'] && count($admins) > 1): ?>
                        <button class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-id="<?= (int)$admin['id'] ?>"
                                data-username="<?= sanitize($admin['username']) ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="admin_id" id="editAdminId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="editUsername" class="form-control" pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control" maxlength="150">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="pwdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="admin_id" id="pwdAdminId">
                    <p class="text-muted small mb-3">Changing password for: <strong id="pwdAdminName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete admin <strong id="deleteAdminName"></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="admin_id" id="deleteAdminId">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Populate Edit modal
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editAdminId').value  = btn.dataset.id;
    document.getElementById('editUsername').value = btn.dataset.username;
    document.getElementById('editEmail').value    = btn.dataset.email;
});
// Populate Password modal
document.getElementById('pwdModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('pwdAdminId').value   = btn.dataset.id;
    document.getElementById('pwdAdminName').textContent = btn.dataset.username;
});
// Populate Delete modal
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('deleteAdminId').value   = btn.dataset.id;
    document.getElementById('deleteAdminName').textContent = btn.dataset.username;
});
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
