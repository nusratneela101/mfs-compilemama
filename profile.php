<?php
/**
 * User Profile — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/subscription.php';

startSecureSession();
requireLogin();

$userId = (int)$_SESSION['user_id'];
$user   = getCurrentUser();
$db     = getDB();

$error   = '';
$success = '';

// Handle PIN change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pin'])) {
    $token      = $_POST['csrf_token'] ?? '';
    $currentPin = $_POST['current_pin'] ?? '';
    $newPin     = $_POST['new_pin'] ?? '';
    $confirmPin = $_POST['confirm_pin'] ?? '';

    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } elseif (strlen($newPin) < PIN_MIN_LENGTH || strlen($newPin) > PIN_MAX_LENGTH || !ctype_digit($newPin)) {
        $error = 'নতুন PIN ' . PIN_MIN_LENGTH . '–' . PIN_MAX_LENGTH . ' সংখ্যার হতে হবে।';
    } elseif ($newPin !== $confirmPin) {
        $error = 'নতুন PIN এবং কনফার্ম PIN মিলছে না।';
    } else {
        $stmt = $db->prepare("SELECT pin_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !verifyPIN($currentPin, $row['pin_hash'])) {
            $error = 'বর্তমান PIN সঠিক নয়।';
        } else {
            $hash  = hashPIN($newPin);
            $stmt  = $db->prepare("UPDATE users SET pin_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $stmt->close();
            $success = 'PIN সফলভাবে পরিবর্তন হয়েছে।';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $token = $_POST['csrf_token'] ?? '';
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } elseif (strlen($name) < 2) {
        $error = 'সঠিক নাম দিন।';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'সঠিক ইমেইল দিন।';
    } else {
        $emailVal = $email ?: null;
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->bind_param('ssi', $name, $emailVal, $userId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_name'] = $name;
        $user = getCurrentUser();
        $success = 'প্রোফাইল আপডেট সফল।';
    }
}

// Subscription info
$hasSub   = checkSubscription($userId);
$daysLeft = $hasSub ? getSubscriptionDaysLeft($userId) : 0;
$subInfo  = getSubscriptionDetails($userId);

// Payment history
$stmt = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $userId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrfToken = generateCSRFToken();
$pageTitle  = 'প্রোফাইল';
$bodyClass  = 'profile-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row g-4">

        <!-- Profile Card -->
        <div class="col-lg-4">
            <div class="form-card text-center mb-4">
                <div class="profile-avatar mx-auto mb-3">👤</div>
                <h4 class="fw-bold"><?= sanitize($user['name'] ?? '') ?></h4>
                <p class="text-muted mb-1"><i class="fas fa-phone me-1"></i><?= sanitize($user['phone'] ?? '') ?></p>
                <?php if ($user['email']): ?>
                <p class="text-muted mb-1"><i class="fas fa-envelope me-1"></i><?= sanitize($user['email']) ?></p>
                <?php endif; ?>
                <p class="text-muted small"><i class="fas fa-calendar me-1"></i>যোগদান: <?= formatDate($user['created_at'] ?? '', 'd M Y') ?></p>
                <span class="badge bg-success">✅ সক্রিয় অ্যাকাউন্ট</span>
            </div>

            <!-- Subscription Widget -->
            <div class="form-card">
                <h6 class="fw-bold mb-3"><i class="fas fa-crown me-2 text-warning"></i>সাবস্ক্রিপশন</h6>
                <?php if ($hasSub): ?>
                <div class="sub-widget sub-active rounded-3 p-3 text-white mb-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fw-bold">✅ সক্রিয়</div>
                            <small class="opacity-75">মেয়াদ: <?= formatDate($subInfo['end_date'], 'd M Y') ?></small>
                        </div>
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:2rem;line-height:1"><?= $daysLeft ?></div>
                            <small class="opacity-75">দিন</small>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="sub-widget sub-expired rounded-3 p-3 text-white mb-3">
                    <div class="fw-bold">❌ সাবস্ক্রিপশন নেই</div>
                </div>
                <?php endif; ?>
                <a href="/subscribe.php" class="btn btn-primary w-100 rounded-pill btn-sm fw-bold">
                    <i class="fas fa-crown me-1"></i>সাবস্ক্রাইব / নবায়ন
                </a>
            </div>
        </div>

        <!-- Edit Forms -->
        <div class="col-lg-8">
            <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss rounded-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-auto-dismiss rounded-3">
                <i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?>
            </div>
            <?php endif; ?>

            <!-- Update Profile -->
            <div class="form-card mb-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-user-edit me-2 text-primary"></i>প্রোফাইল আপডেট</h5>
                <form method="POST" action="/profile.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">নাম</label>
                            <input type="text" class="form-control" name="name" required
                                   value="<?= sanitize($user['name'] ?? '') ?>" minlength="2" maxlength="100">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">ফোন (পরিবর্তনযোগ্য নয়)</label>
                            <input type="tel" class="form-control bg-light" readonly
                                   value="<?= sanitize($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">ইমেইল (ঐচ্ছিক)</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= sanitize($user['email'] ?? '') ?>" maxlength="150">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 mt-3">
                        <i class="fas fa-save me-1"></i>সংরক্ষণ করুন
                    </button>
                </form>
            </div>

            <!-- Change PIN -->
            <div class="form-card mb-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-key me-2 text-primary"></i>PIN পরিবর্তন</h5>
                <form method="POST" action="/profile.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="change_pin" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">বর্তমান PIN</label>
                            <input type="password" class="form-control" name="current_pin" required
                                   minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                                   inputmode="numeric" placeholder="বর্তমান PIN">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">নতুন PIN</label>
                            <input type="password" class="form-control" name="new_pin" id="new_pin" required
                                   minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                                   inputmode="numeric" pattern="[0-9]{<?= PIN_MIN_LENGTH ?>,<?= PIN_MAX_LENGTH ?>}"
                                   placeholder="নতুন PIN">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-bold">নতুন PIN নিশ্চিত করুন</label>
                            <input type="password" class="form-control" name="confirm_pin" id="pin_confirm" required
                                   minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                                   inputmode="numeric" placeholder="নতুন PIN পুনরায়">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 mt-3">
                        <i class="fas fa-key me-1"></i>PIN পরিবর্তন করুন
                    </button>
                </form>
            </div>

            <!-- Payment History -->
            <?php if (!empty($payments)): ?>
            <div class="form-card">
                <h5 class="fw-bold mb-4"><i class="fas fa-receipt me-2 text-primary"></i>পেমেন্ট ইতিহাস</h5>
                <div class="table-responsive">
                    <table class="table table-hover tx-table">
                        <thead><tr><th>তারিখ</th><th>পরিমাণ</th><th>পদ্ধতি</th><th>Transaction ID</th><th>অবস্থা</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $p):
                                $sClass = ['pending'=>'bg-warning','completed'=>'bg-success','failed'=>'bg-danger'][$p['status']] ?? 'bg-secondary';
                                $sLabel = ['pending'=>'অপেক্ষমান','completed'=>'সম্পন্ন','failed'=>'ব্যর্থ'][$p['status']] ?? $p['status'];
                            ?>
                            <tr>
                                <td><?= formatDate($p['created_at'], 'd M y') ?></td>
                                <td class="fw-bold">৳<?= number_format($p['amount'],0) ?></td>
                                <td><?= strtoupper(sanitize($p['payment_method'])) ?></td>
                                <td><code><?= sanitize($p['transaction_id']) ?></code></td>
                                <td><span class="badge <?= $sClass ?>"><?= $sLabel ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
