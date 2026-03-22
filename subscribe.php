<?php
/**
 * Subscribe Page — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/subscription.php';

startSecureSession();
requireLogin();

$user    = getCurrentUser();
$userId  = (int)$_SESSION['user_id'];
$subInfo = getSubscriptionDetails($userId);
$hasSub  = checkSubscription($userId);
$daysLeft = $hasSub ? getSubscriptionDaysLeft($userId) : 0;

$error   = '';
$success = '';

$msg = $_GET['msg'] ?? '';
if ($msg === 'verified')              $success = 'ফোন নম্বর যাচাই সফল! এখন সাবস্ক্রিপশন নিন।';
if ($msg === 'subscription_required') $error   = 'সার্ভিস ব্যবহার করতে সাবস্ক্রিপশন প্রয়োজন।';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        $error = 'নিরাপত্তা যাচাই ব্যর্থ।';
    } else {
        $method = $_POST['payment_method'] ?? 'bkash';
        $txId   = trim($_POST['transaction_id'] ?? '');

        $allowed = ['bkash','nagad','rocket','manual'];
        if (!in_array($method, $allowed, true)) $method = 'bkash';

        if (empty($txId) || strlen($txId) < 6) {
            $error = 'সঠিক ট্র্যানজেকশন আইডি দিন (কমপক্ষে ৬ অক্ষর)।';
        } else {
            $db = getDB();
            // Check duplicate transaction ID
            $stmt = $db->prepare("SELECT id FROM payments WHERE transaction_id = ? LIMIT 1");
            $stmt->bind_param('s', $txId);
            $stmt->execute();
            $dup = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($dup) {
                $error = 'এই ট্র্যানজেকশন আইডি ইতিমধ্যে জমা দেওয়া হয়েছে।';
            } else {
                $amount = SUB_AMOUNT;
                $stmt   = $db->prepare(
                    "INSERT INTO payments (user_id, amount, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, 'pending')"
                );
                $stmt->bind_param('idss', $userId, $amount, $method, $txId);
                if ($stmt->execute()) {
                    $success = 'পেমেন্ট জমা সফল! অ্যাডমিন যাচাই করার পরে সাবস্ক্রিপশন সক্রিয় হবে (সাধারণত ১–২৪ ঘণ্টা)।';
                } else {
                    $error = 'পেমেন্ট জমা দিতে সমস্যা হয়েছে। পুনরায় চেষ্টা করুন।';
                }
                $stmt->close();
            }
        }
    }
}

// Get pending payments for this user
$db = getDB();
$stmt = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $userId);
$stmt->execute();
$pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrfToken = generateCSRFToken();
$pageTitle  = 'সাবস্ক্রিপশন';
$bodyClass  = 'subscribe-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary"><i class="fas fa-crown me-2"></i>সাবস্ক্রিপশন</h2>
                <p class="text-muted">মাত্র ৳<?= SUB_AMOUNT ?>/মাসে সব MFS সার্ভিস ব্যবহার করুন</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss rounded-3">
                <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success rounded-3">
                <i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?>
            </div>
            <?php endif; ?>

            <!-- Current Subscription Status -->
            <?php if ($hasSub && $subInfo): ?>
            <div class="sub-widget sub-active mb-4 text-white rounded-4 p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="fw-bold mb-1">✅ সক্রিয় সাবস্ক্রিপশন</h5>
                        <p class="mb-0 opacity-75">মেয়াদ: <?= formatDate($subInfo['end_date'], 'd M Y') ?></p>
                    </div>
                    <div class="col-auto text-center">
                        <div class="sub-days"><?= $daysLeft ?></div>
                        <small class="opacity-75">দিন বাকি</small>
                    </div>
                </div>
            </div>
            <?php elseif (!$hasSub): ?>
            <div class="sub-widget sub-expired mb-4 text-white rounded-4 p-4">
                <h5 class="fw-bold mb-1">❌ সাবস্ক্রিপশন নেই</h5>
                <p class="mb-0 opacity-75">নিচের ফর্ম পূরণ করে সাবস্ক্রিপশন নিন।</p>
            </div>
            <?php endif; ?>

            <!-- Payment Instructions -->
            <div class="form-card mb-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-info-circle me-2 text-primary"></i>পেমেন্ট নির্দেশনা</h5>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3" style="background:#fce4ef;border:2px solid #E2136E">
                            <div class="fw-bold text-primary mb-1">💰 bKash</div>
                            <div class="fs-5 fw-bold"><?= sanitize(BKASH_NUMBER) ?></div>
                            <small class="text-muted">Send Money করুন</small>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 rounded-3" style="background:#fff3e0;border:2px solid #F16528">
                            <div class="fw-bold mb-1" style="color:#F16528">🟠 Nagad</div>
                            <div class="fs-5 fw-bold"><?= sanitize(NAGAD_NUMBER) ?></div>
                            <small class="text-muted">Send Money করুন</small>
                        </div>
                    </div>
                </div>

                <div class="alert alert-mfs mb-4">
                    <strong>📋 নির্দেশাবলী:</strong>
                    <ol class="mb-0 mt-2">
                        <li>উপরের যেকোনো একটি নম্বরে <strong>৳<?= SUB_AMOUNT ?></strong> পাঠান</li>
                        <li>পাঠানোর পর <strong>Transaction ID</strong> কপি করুন</li>
                        <li>নিচের ফর্মে Transaction ID এবং পেমেন্ট পদ্ধতি দিন</li>
                        <li>Admin যাচাই করার পর সাবস্ক্রিপশন সক্রিয় হবে</li>
                    </ol>
                </div>

                <!-- Payment Form -->
                <form method="POST" action="/subscribe.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="bkash">

                    <div class="mb-4">
                        <label class="form-label fw-bold">পেমেন্ট পদ্ধতি</label>
                        <div class="row g-2">
                            <?php
                            $methods = [
                                ['id'=>'bkash',  'label'=>'💰 bKash',  'color'=>'#E2136E'],
                                ['id'=>'nagad',  'label'=>'🟠 Nagad',  'color'=>'#F16528'],
                                ['id'=>'rocket', 'label'=>'🚀 Rocket', 'color'=>'#8B1A7C'],
                                ['id'=>'manual', 'label'=>'🏦 ম্যানুয়াল','color'=>'#6c757d'],
                            ];
                            foreach ($methods as $m): ?>
                            <div class="col-6 col-md-3">
                                <div class="payment-method-card text-center <?= $m['id']==='bkash'?'active':'' ?>"
                                     data-method="<?= $m['id'] ?>"
                                     style="<?= $m['id']==='bkash'?"border-color:{$m['color']};background:{$m['color']}11":'' ?>">
                                    <div class="fw-bold" style="color:<?= $m['color'] ?>"><?= $m['label'] ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="transaction_id" class="form-label fw-bold">
                            <i class="fas fa-receipt me-1 text-primary"></i>Transaction ID
                        </label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id"
                               placeholder="e.g. 8FG4KP2LX1" required minlength="6" maxlength="100"
                               value="<?= sanitize($_POST['transaction_id'] ?? '') ?>">
                        <div class="form-text">পেমেন্ট করার পরে প্রাপ্ত Transaction ID দিন</div>
                        <div class="invalid-feedback">সঠিক Transaction ID দিন।</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold">
                        <i class="fas fa-paper-plane me-2"></i>পেমেন্ট জমা দিন
                    </button>
                </form>
            </div>

            <!-- Payment History -->
            <?php if (!empty($pendingPayments)): ?>
            <div class="form-card">
                <h5 class="fw-bold mb-4"><i class="fas fa-history me-2 text-primary"></i>পেমেন্ট ইতিহাস</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>তারিখ</th>
                                <th>পরিমাণ</th>
                                <th>পদ্ধতি</th>
                                <th>Transaction ID</th>
                                <th>অবস্থা</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingPayments as $p):
                                $sClass = ['pending'=>'bg-warning','completed'=>'bg-success','failed'=>'bg-danger'][$p['status']] ?? 'bg-secondary';
                                $sLabel = ['pending'=>'অপেক্ষমান','completed'=>'সম্পন্ন','failed'=>'ব্যর্থ'][$p['status']] ?? $p['status'];
                            ?>
                            <tr>
                                <td><?= formatDate($p['created_at'], 'd M y') ?></td>
                                <td class="fw-bold">৳<?= sanitize(number_format($p['amount'], 0)) ?></td>
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
