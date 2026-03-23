<?php
/**
 * Wallet Core Functions
 * MFS Compilemama — Digital Wallet / E-Wallet
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Wallet fee constants
define('WALLET_FREE_LIMIT',      10000.00);   // First ৳10,000 free
define('WALLET_FEE_PER_TX',      3.00);       // ৳3 per transaction after free limit
define('WALLET_MIN_AMOUNT',      10.00);      // Minimum transaction ৳10
define('WALLET_MAX_AMOUNT',      50000.00);   // Maximum single transaction ৳50,000

/**
 * Get or auto-create wallet for a user.
 */
function getOrCreateWallet(int $userId): array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($wallet) {
        return $wallet;
    }

    // Auto-create
    $stmt = $db->prepare(
        "INSERT INTO wallets (user_id, balance, total_added, total_withdrawn, total_transferred, total_fees, free_limit_used, status)
         VALUES (?, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'active')"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM wallets WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $newId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $wallet;
}

/**
 * Get current wallet balance for a user.
 */
function getWalletBalance(int $userId): float {
    $wallet = getOrCreateWallet($userId);
    return (float)$wallet['balance'];
}

/**
 * Calculate transaction fee.
 * Free up to WALLET_FREE_LIMIT total volume; ৳3/tx after that.
 */
function calculateFee(int $userId): float {
    $wallet = getOrCreateWallet($userId);
    $used   = (float)$wallet['free_limit_used'];
    return $used >= WALLET_FREE_LIMIT ? WALLET_FEE_PER_TX : 0.00;
}

/**
 * Generate a unique wallet reference ID.
 */
function generateWalletReference(): string {
    return 'WLT' . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Add money to wallet from an MFS provider (simulated).
 *
 * @return array ['success'=>bool, 'message'=>string, 'reference_id'=>string]
 */
function addMoney(int $userId, float $amount, string $mfsProvider, string $mfsAccount): array {
    if ($amount < WALLET_MIN_AMOUNT) {
        return ['success' => false, 'message' => 'সর্বনিম্ন পরিমাণ ৳' . number_format(WALLET_MIN_AMOUNT, 0) . ' হতে হবে।'];
    }
    if ($amount > WALLET_MAX_AMOUNT) {
        return ['success' => false, 'message' => 'সর্বোচ্চ পরিমাণ ৳' . number_format(WALLET_MAX_AMOUNT, 0) . ' হতে পারবে।'];
    }

    $db     = getDB();
    $wallet = getOrCreateWallet($userId);

    if ($wallet['status'] !== 'active') {
        return ['success' => false, 'message' => 'আপনার ওয়ালেট সক্রিয় নয়।'];
    }

    $fee          = calculateFee($userId);
    $balanceBefore = (float)$wallet['balance'];
    $balanceAfter  = $balanceBefore + $amount;   // fee not deducted on add
    $referenceId   = generateWalletReference();

    $db->begin_transaction();
    try {
        // Update wallet balance and stats
        $stmt = $db->prepare(
            "UPDATE wallets
             SET balance = balance + ?,
                 total_added = total_added + ?,
                 free_limit_used = free_limit_used + ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('dddi', $amount, $amount, $amount, $wallet['id']);
        $stmt->execute();
        $stmt->close();

        // If fee applies, deduct fee
        if ($fee > 0) {
            $balanceAfter -= $fee;
            $stmt = $db->prepare(
                "UPDATE wallets
                 SET balance = balance - ?,
                     total_fees = total_fees + ?,
                     updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('ddi', $fee, $fee, $wallet['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Record add_money transaction
        $desc = 'যোগ করা হয়েছে ' . $mfsProvider . ' থেকে (' . $mfsAccount . ')';
        $stmt = $db->prepare(
            "INSERT INTO wallet_transactions
             (wallet_id, user_id, type, amount, fee, mfs_provider, mfs_account, reference_id,
              description, balance_before, balance_after, status)
             VALUES (?, ?, 'add_money', ?, ?, ?, ?, ?, ?, ?, ?, 'completed')"
        );
        $stmt->bind_param(
            'iiddssssddd',
            $wallet['id'], $userId, $amount, $fee,
            $mfsProvider, $mfsAccount, $referenceId,
            $desc, $balanceBefore, $balanceAfter
        );
        $stmt->execute();
        $stmt->close();

        // Record fee transaction separately if applicable
        if ($fee > 0) {
            $feeRef  = generateWalletReference();
            $feeDesc = 'সার্ভিস চার্জ (রেফ: ' . $referenceId . ')';
            $afterFeeRecord = $balanceBefore + $amount;  // before fee deduction for fee tx
            $stmt = $db->prepare(
                "INSERT INTO wallet_transactions
                 (wallet_id, user_id, type, amount, fee, reference_id,
                  description, balance_before, balance_after, status)
                 VALUES (?, ?, 'fee', ?, 0.00, ?, ?, ?, ?, 'completed')"
            );
            $stmt->bind_param(
                'iidssdd',
                $wallet['id'], $userId, $fee, $feeRef,
                $feeDesc, $afterFeeRecord, $balanceAfter
            );
            $stmt->execute();
            $stmt->close();
        }

        $db->commit();
        return ['success' => true, 'message' => 'টাকা সফলভাবে যোগ হয়েছে।', 'reference_id' => $referenceId, 'fee' => $fee, 'balance_after' => $balanceAfter];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'সমস্যা হয়েছে। আবার চেষ্টা করুন।'];
    }
}

/**
 * Withdraw money from wallet to an MFS provider (simulated).
 *
 * @return array ['success'=>bool, 'message'=>string, 'reference_id'=>string]
 */
function withdrawMoney(int $userId, float $amount, string $mfsProvider, string $mfsAccount): array {
    if ($amount < WALLET_MIN_AMOUNT) {
        return ['success' => false, 'message' => 'সর্বনিম্ন পরিমাণ ৳' . number_format(WALLET_MIN_AMOUNT, 0) . ' হতে হবে।'];
    }
    if ($amount > WALLET_MAX_AMOUNT) {
        return ['success' => false, 'message' => 'সর্বোচ্চ পরিমাণ ৳' . number_format(WALLET_MAX_AMOUNT, 0) . ' হতে পারবে।'];
    }

    $db     = getDB();
    $wallet = getOrCreateWallet($userId);

    if ($wallet['status'] !== 'active') {
        return ['success' => false, 'message' => 'আপনার ওয়ালেট সক্রিয় নয়।'];
    }

    $fee           = calculateFee($userId);
    $totalDeduct   = $amount + $fee;
    $balanceBefore = (float)$wallet['balance'];

    if ($balanceBefore < $totalDeduct) {
        return ['success' => false, 'message' => 'পর্যাপ্ত ব্যালেন্স নেই। ব্যালেন্স: ৳' . number_format($balanceBefore, 2) . ', প্রয়োজন: ৳' . number_format($totalDeduct, 2)];
    }

    $balanceAfter = $balanceBefore - $totalDeduct;
    $referenceId  = generateWalletReference();

    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            "UPDATE wallets
             SET balance = balance - ?,
                 total_withdrawn = total_withdrawn + ?,
                 total_fees = total_fees + ?,
                 free_limit_used = free_limit_used + ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ddddi', $totalDeduct, $amount, $fee, $amount, $wallet['id']);
        $stmt->execute();
        $stmt->close();

        $desc = 'উইথড্রো — ' . $mfsProvider . ' (' . $mfsAccount . ')';
        $stmt = $db->prepare(
            "INSERT INTO wallet_transactions
             (wallet_id, user_id, type, amount, fee, mfs_provider, mfs_account, reference_id,
              description, balance_before, balance_after, status)
             VALUES (?, ?, 'withdraw', ?, ?, ?, ?, ?, ?, ?, ?, 'completed')"
        );
        $stmt->bind_param(
            'iiddssssddd',
            $wallet['id'], $userId, $amount, $fee,
            $mfsProvider, $mfsAccount, $referenceId,
            $desc, $balanceBefore, $balanceAfter
        );
        $stmt->execute();
        $stmt->close();

        $db->commit();
        return ['success' => true, 'message' => 'টাকা সফলভাবে উইথড্রো হয়েছে।', 'reference_id' => $referenceId, 'fee' => $fee, 'balance_after' => $balanceAfter];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'সমস্যা হয়েছে। আবার চেষ্টা করুন।'];
    }
}

/**
 * Transfer money wallet-to-wallet between users.
 *
 * @return array ['success'=>bool, 'message'=>string, 'reference_id'=>string]
 */
function transferMoney(int $fromUserId, int $toUserId, float $amount): array {
    if ($fromUserId === $toUserId) {
        return ['success' => false, 'message' => 'নিজের ওয়ালেটে ট্রান্সফার করা যাবে না।'];
    }
    if ($amount < WALLET_MIN_AMOUNT) {
        return ['success' => false, 'message' => 'সর্বনিম্ন পরিমাণ ৳' . number_format(WALLET_MIN_AMOUNT, 0) . ' হতে হবে।'];
    }
    if ($amount > WALLET_MAX_AMOUNT) {
        return ['success' => false, 'message' => 'সর্বোচ্চ পরিমাণ ৳' . number_format(WALLET_MAX_AMOUNT, 0) . ' হতে পারবে।'];
    }

    $db         = getDB();
    $fromWallet = getOrCreateWallet($fromUserId);
    $toWallet   = getOrCreateWallet($toUserId);

    if ($fromWallet['status'] !== 'active') {
        return ['success' => false, 'message' => 'আপনার ওয়ালেট সক্রিয় নয়।'];
    }
    if ($toWallet['status'] !== 'active') {
        return ['success' => false, 'message' => 'প্রাপকের ওয়ালেট সক্রিয় নয়।'];
    }

    $fee           = calculateFee($fromUserId);
    $totalDeduct   = $amount + $fee;
    $fromBefore    = (float)$fromWallet['balance'];

    if ($fromBefore < $totalDeduct) {
        return ['success' => false, 'message' => 'পর্যাপ্ত ব্যালেন্স নেই। ব্যালেন্স: ৳' . number_format($fromBefore, 2) . ', প্রয়োজন: ৳' . number_format($totalDeduct, 2)];
    }

    $fromAfter   = $fromBefore - $totalDeduct;
    $toBefore    = (float)$toWallet['balance'];
    $toAfter     = $toBefore + $amount;
    $referenceId = generateWalletReference();

    $db->begin_transaction();
    try {
        // Deduct from sender
        $stmt = $db->prepare(
            "UPDATE wallets
             SET balance = balance - ?,
                 total_transferred = total_transferred + ?,
                 total_fees = total_fees + ?,
                 free_limit_used = free_limit_used + ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ddddi', $totalDeduct, $amount, $fee, $amount, $fromWallet['id']);
        $stmt->execute();
        $stmt->close();

        // Add to receiver
        $stmt = $db->prepare(
            "UPDATE wallets
             SET balance = balance + ?,
                 total_transferred = total_transferred + ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ddi', $amount, $amount, $toWallet['id']);
        $stmt->execute();
        $stmt->close();

        // Log sender's transfer_out
        $descOut = 'ট্রান্সফার করা হয়েছে (রেফ: ' . $referenceId . ')';
        $stmt = $db->prepare(
            "INSERT INTO wallet_transactions
             (wallet_id, user_id, type, amount, fee, recipient_user_id, reference_id,
              description, balance_before, balance_after, status)
             VALUES (?, ?, 'transfer_out', ?, ?, ?, ?, ?, ?, ?, 'completed')"
        );
        $stmt->bind_param(
            'iiddissdd',
            $fromWallet['id'], $fromUserId, $amount, $fee,
            $toUserId, $referenceId, $descOut, $fromBefore, $fromAfter
        );
        $stmt->execute();
        $stmt->close();

        // Log receiver's transfer_in
        $descIn = 'ট্রান্সফার পেয়েছেন (রেফ: ' . $referenceId . ')';
        $stmt = $db->prepare(
            "INSERT INTO wallet_transactions
             (wallet_id, user_id, type, amount, fee, recipient_user_id, reference_id,
              description, balance_before, balance_after, status)
             VALUES (?, ?, 'transfer_in', ?, 0.00, ?, ?, ?, ?, ?, 'completed')"
        );
        $stmt->bind_param(
            'iidiisdd',
            $toWallet['id'], $toUserId, $amount,
            $fromUserId, $referenceId, $descIn, $toBefore, $toAfter
        );
        $stmt->execute();
        $stmt->close();

        $db->commit();
        return ['success' => true, 'message' => 'ট্রান্সফার সফল হয়েছে।', 'reference_id' => $referenceId, 'fee' => $fee, 'balance_after' => $fromAfter];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'সমস্যা হয়েছে। আবার চেষ্টা করুন।'];
    }
}

/**
 * Get paginated wallet transaction history for a user.
 */
function getWalletTransactions(int $userId, int $limit = 20, int $offset = 0, string $type = '', string $dateFrom = '', string $dateTo = ''): array {
    $db     = getDB();
    $params = [$userId];
    $types  = 'i';
    $where  = 'WHERE wt.user_id = ?';

    if ($type) {
        $where   .= ' AND wt.type = ?';
        $types   .= 's';
        $params[] = $type;
    }
    if ($dateFrom) {
        $where   .= ' AND DATE(wt.created_at) >= ?';
        $types   .= 's';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where   .= ' AND DATE(wt.created_at) <= ?';
        $types   .= 's';
        $params[] = $dateTo;
    }

    // Total count
    $stmt = $db->prepare("SELECT COUNT(*) FROM wallet_transactions wt $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();

    // Rows
    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';
    $stmt = $db->prepare(
        "SELECT wt.*, u.name AS recipient_name, u.phone AS recipient_phone
         FROM wallet_transactions wt
         LEFT JOIN users u ON u.id = wt.recipient_user_id
         $where
         ORDER BY wt.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['total' => $total, 'rows' => $rows];
}

/**
 * Verify wallet PIN for a user.
 */
function verifyWalletPin(int $userId, string $pin): bool {
    $wallet = getOrCreateWallet($userId);
    if (empty($wallet['pin_hash'])) {
        return false;
    }
    return password_verify($pin, $wallet['pin_hash']);
}

/**
 * Set or change wallet PIN for a user.
 */
function setWalletPin(int $userId, string $pin): bool {
    if (strlen($pin) < 4 || strlen($pin) > 6 || !ctype_digit($pin)) {
        return false;
    }
    $db      = getDB();
    $wallet  = getOrCreateWallet($userId);
    $pinHash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt    = $db->prepare("UPDATE wallets SET pin_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $pinHash, $wallet['id']);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Check whether the user has set a wallet PIN.
 */
function walletHasPin(int $userId): bool {
    $wallet = getOrCreateWallet($userId);
    return !empty($wallet['pin_hash']);
}

/**
 * Find a user by phone number for transfers.
 * Returns user array (id, name, phone) or null.
 */
function findUserByPhone(string $phone): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name, phone FROM users WHERE phone = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get wallet transaction type label in Bangla.
 */
function walletTypeLabel(string $type): string {
    $labels = [
        'add_money'    => 'অ্যাড মানি',
        'withdraw'     => 'উইথড্রো',
        'transfer_in'  => 'ট্রান্সফার (প্রাপ্ত)',
        'transfer_out' => 'ট্রান্সফার (প্রেরণ)',
        'fee'          => 'সার্ভিস চার্জ',
    ];
    return $labels[$type] ?? $type;
}

/**
 * Get wallet transaction type icon (emoji).
 */
function walletTypeIcon(string $type): string {
    $icons = [
        'add_money'    => '📥',
        'withdraw'     => '📤',
        'transfer_in'  => '➡️',
        'transfer_out' => '⬅️',
        'fee'          => '💸',
    ];
    return $icons[$type] ?? '💰';
}
