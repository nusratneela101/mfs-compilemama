<?php
/**
 * Subscription Helper
 * MFS Compilemama
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Check if user has an active subscription
 */
function checkSubscription(int $userId): bool {
    $db  = getDB();
    $now = date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= ? LIMIT 1"
    );
    $stmt->bind_param('is', $userId, $now);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return !empty($res);
}

/**
 * Get days remaining on the user's active subscription
 */
function getSubscriptionDaysLeft(int $userId): int {
    $db  = getDB();
    $now = date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT end_date FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= ? ORDER BY end_date DESC LIMIT 1"
    );
    $stmt->bind_param('is', $userId, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return 0;
    $diff = (strtotime($row['end_date']) - strtotime($now)) / 86400;
    return (int)ceil($diff);
}

/**
 * Activate a subscription for a user.
 * If user already has an active subscription, extend it.
 */
function activateSubscription(int $userId, string $paymentMethod = 'bkash', float $amount = SUB_AMOUNT): bool {
    $db  = getDB();
    $now = date('Y-m-d');

    // Check existing active subscription to extend
    $stmt = $db->prepare(
        "SELECT id, end_date FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= ? ORDER BY end_date DESC LIMIT 1"
    );
    $stmt->bind_param('is', $userId, $now);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Extend existing subscription
        $newEnd = date('Y-m-d', strtotime($existing['end_date'] . ' +' . SUB_DAYS . ' days'));
        $stmt   = $db->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?");
        $stmt->bind_param('si', $newEnd, $existing['id']);
        $result = $stmt->execute();
        $stmt->close();
    } else {
        // Create new subscription
        $start = $now;
        $end   = date('Y-m-d', strtotime('+' . SUB_DAYS . ' days'));
        $stmt  = $db->prepare(
            "INSERT INTO subscriptions (user_id, start_date, end_date, amount, status, payment_method) VALUES (?, ?, ?, ?, 'active', ?)"
        );
        $stmt->bind_param('issds', $userId, $start, $end, $amount, $paymentMethod);
        $result = $stmt->execute();
        $stmt->close();
    }

    // Also activate user account if inactive
    $stmtU = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'inactive'");
    $stmtU->bind_param('i', $userId);
    $stmtU->execute();
    $stmtU->close();

    return $result;
}

/**
 * Get full subscription details for a user
 */
function getSubscriptionDetails(int $userId): ?array {
    $db  = getDB();
    $now = date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT s.*, p.transaction_id, p.payment_method AS pay_method
         FROM subscriptions s
         LEFT JOIN payments p ON p.user_id = s.user_id AND p.status = 'completed'
         WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= ?
         ORDER BY s.end_date DESC LIMIT 1"
    );
    $stmt->bind_param('is', $userId, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Expire all past-due subscriptions (run via cron)
 */
function expireSubscriptions(): int {
    $db  = getDB();
    $now = date('Y-m-d');
    $res = $db->query("UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND end_date < '{$now}'");
    return $db->affected_rows;
}
