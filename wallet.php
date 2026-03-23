<?php
/**
 * Wallet Dashboard — MFS Compilemama
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/location.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wallet.php';

startSecureSession();
requireSubscription();

$userId = (int)$_SESSION['user_id'];
$user   = getCurrentUser();

// Redirect to PIN setup if no PIN set
if (!walletHasPin($userId)) {
    redirect('/wallet-setup.php');
}

$wallet  = getOrCreateWallet($userId);
$txData  = getWalletTransactions($userId, 10, 0);
$recentTx = $txData['rows'];

$freePct = min(100, ($wallet['free_limit_used'] / WALLET_FREE_LIMIT) * 100);
$freeRemaining = max(0, WALLET_FREE_LIMIT - $wallet['free_limit_used']);

$pageTitle = 'আমার ওয়ালেট';
$bodyClass = 'wallet-page';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Wallet Balance Card -->
    <div class="mb-4 rounded-4 p-4 text-white"
         style="background:linear-gradient(135deg,#E2136E 0%,#8B1A7C 100%);box-shadow:0 8px 32px rgba(226,19,110,.35)">
        <div class="row align-items-center">
            <div class="col">
                <p class="mb-1 opacity-75 small">ওয়ালেট ব্যালেন্স</p>
                <h2 class="fw-bold mb-0" style="font-size:2.5rem">
                    ৳<?= number_format((float)$wallet['balance'], 2) ?>
                </h2>
                <p class="mb-0 mt-1 opacity-75 small">
                    <?= sanitize($user['name'] ?? '') ?> — <?= sanitize($user['phone'] ?? '') ?>
                </p>
            </div>
            <div class="col-auto">
                <div style="font-size:4rem;opacity:.9">💰</div>
            </div>
        </div>
        <div class="d-flex gap-3 mt-4 flex-wrap">
            <a href="/wallet-add.php" class="btn btn-light text-primary fw-bold rounded-pill px-3">
                📥 অ্যাড মানি
            </a>
            <a href="/wallet-withdraw.php" class="btn btn-outline-light rounded-pill px-3">
                📤 উইথড্রো
            </a>
            <a href="/wallet-transfer.php" class="btn btn-outline-light rounded-pill px-3">
                🔄 ট্রান্সফার
            </a>
            <a href="/wallet-history.php" class="btn btn-outline-light rounded-pill px-3">
                📋 ইতিহাস
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto" style="background:#e8f5e9">📥</div>
                <div class="stat-value">৳<?= number_format((float)$wallet['total_added'], 0) ?></div>
                <div class="stat-label">মোট যোগ</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto" style="background:#fff3e0">📤</div>
                <div class="stat-value">৳<?= number_format((float)$wallet['total_withdrawn'], 0) ?></div>
                <div class="stat-label">মোট উইথড্রো</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto" style="background:#e3f2fd">🔄</div>
                <div class="stat-value">৳<?= number_format((float)$wallet['total_transferred'], 0) ?></div>
                <div class="stat-label">মোট ট্রান্সফার</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-icon mx-auto" style="background:#fce4ef">💸</div>
                <div class="stat-value">৳<?= number_format((float)$wallet['total_fees'], 0) ?></div>
                <div class="stat-label">মোট চার্জ</div>
            </div>
        </div>
    </div>

    <!-- Free Limit Progress -->
    <div class="stat-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">🆓 ফ্রি লিমিট ব্যবহার</h6>
            <small class="text-muted">
                ৳<?= number_format((float)$wallet['free_limit_used'], 0) ?> /
                ৳<?= number_format(WALLET_FREE_LIMIT, 0) ?>
            </small>
        </div>
        <div class="progress rounded-pill" style="height:12px">
            <div class="progress-bar bg-primary rounded-pill"
                 style="width:<?= number_format($freePct, 1) ?>%"></div>
        </div>
        <div class="mt-2 small text-muted">
            <?php if ($freeRemaining > 0): ?>
                আরও ৳<?= number_format($freeRemaining, 0) ?> পর্যন্ত ফ্রি — এরপর ৳<?= WALLET_FEE_PER_TX ?>/লেনদেন চার্জ
            <?php else: ?>
                ফ্রি লিমিট শেষ — প্রতি লেনদেনে ৳<?= WALLET_FEE_PER_TX ?> চার্জ প্রযোজ্য
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="stat-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">📋 সাম্প্রতিক লেনদেন</h5>
            <a href="/wallet-history.php" class="btn btn-sm btn-outline-primary rounded-pill">সব দেখুন</a>
        </div>

        <?php if (empty($recentTx)): ?>
        <div class="text-center py-4 text-muted">
            <div style="font-size:3rem">📭</div>
            <p class="mt-2">এখনো কোনো লেনদেন নেই।</p>
            <a href="/wallet-add.php" class="btn btn-primary rounded-pill">অ্যাড মানি করুন</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover tx-table mb-0">
                <thead>
                    <tr>
                        <th>ধরন</th>
                        <th>পরিমাণ</th>
                        <th>চার্জ</th>
                        <th>ব্যালেন্স</th>
                        <th>তারিখ</th>
                        <th>অবস্থা</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td>
                            <?= walletTypeIcon($tx['type']) ?>
                            <?= walletTypeLabel($tx['type']) ?>
                            <?php if ($tx['mfs_provider']): ?>
                                <div class="small text-muted"><?= sanitize($tx['mfs_provider']) ?></div>
                            <?php elseif ($tx['recipient_name'] && $tx['type'] !== 'fee'): ?>
                                <div class="small text-muted"><?= sanitize($tx['recipient_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold <?= in_array($tx['type'], ['add_money','transfer_in']) ? 'text-success' : 'text-danger' ?>">
                            <?= in_array($tx['type'], ['add_money','transfer_in']) ? '+' : '-' ?>৳<?= number_format((float)$tx['amount'], 2) ?>
                        </td>
                        <td class="small text-muted">
                            <?= $tx['fee'] > 0 ? '৳' . number_format((float)$tx['fee'], 2) : '—' ?>
                        </td>
                        <td>৳<?= number_format((float)$tx['balance_after'], 2) ?></td>
                        <td class="small text-muted"><?= formatDate($tx['created_at'], 'd M, h:ia') ?></td>
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
        <?php endif; ?>
    </div>

    <!-- PIN Settings Link -->
    <div class="text-center mt-3">
        <a href="/wallet-setup.php" class="text-muted small">🔐 PIN পরিবর্তন করুন</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
