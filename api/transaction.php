<?php
/**
 * API: Transaction Handler — MFS Compilemama
 */

define('API_REQUEST', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/subscription.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();

if (!isLoggedIn()) {
    jsonResponse(false, 'লগইন করা প্রয়োজন।', ['redirect' => '/login.php']);
}

$userId = (int)$_SESSION['user_id'];

if (!checkSubscription($userId)) {
    jsonResponse(false, 'সার্ভিস ব্যবহার করতে সাবস্ক্রিপশন প্রয়োজন।', ['redirect' => '/subscribe.php']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

// CSRF check
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'নিরাপত্তা যাচাই ব্যর্থ। পৃষ্ঠাটি রিফ্রেশ করুন।');
}

// Rate limit
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rateLimitCheck($ip . ':' . $userId, 'transaction', RATE_LIMIT_API_PER_MINUTE, 60)) {
    jsonResponse(false, 'অনেক বেশি অনুরোধ করা হয়েছে। একটু অপেক্ষা করুন।');
}

$mfsSlug   = trim($_POST['mfs_provider'] ?? '');
$type      = trim($_POST['type'] ?? '');
$amount    = (float)($_POST['amount'] ?? 0);
$recipient = trim($_POST['recipient'] ?? '');

// Validate MFS provider
$provider = getMFSProvider($mfsSlug);
if (!$provider) {
    jsonResponse(false, 'অবৈধ MFS সার্ভিস।');
}

// Validate type
$allowedTypes = ['send','cashout','recharge','payment','balance'];
if (!in_array($type, $allowedTypes, true)) {
    jsonResponse(false, 'অবৈধ লেনদেনের ধরন।');
}

// Balance check - no amount/recipient needed
if ($type !== 'balance') {
    if ($amount < 1 || $amount > 25000) {
        jsonResponse(false, 'পরিমাণ ১ থেকে ২৫,০০০ টাকার মধ্যে হতে হবে।');
    }
    if (empty($recipient)) {
        jsonResponse(false, 'প্রাপকের নম্বর/আইডি দিন।');
    }
    // Validate phone format for send/cashout/recharge
    if (in_array($type, ['send','cashout','recharge'], true) && !validateBDPhone($recipient)) {
        jsonResponse(false, 'সঠিক বাংলাদেশি নম্বর দিন (01XXXXXXXXX)।');
    }
    // Cannot send to self
    if (in_array($type, ['send','cashout'], true) && $recipient === ($_SESSION['user_phone'] ?? '')) {
        jsonResponse(false, 'নিজের নম্বরে লেনদেন করা যাবে না।');
    }
}

$db        = getDB();
$reference = generateReference();
$status    = 'success'; // Simulated

// Simulate occasional failures (configurable via TRANSACTION_FAILURE_RATE, default 0)
$failureRate = defined('TRANSACTION_FAILURE_RATE') ? (int) TRANSACTION_FAILURE_RATE : 0;
if ($failureRate > 0 && random_int(1, 100) <= $failureRate) {
    $status = 'failed';
}

// Balance check: return simulated balance
if ($type === 'balance') {
    // Simulated balance
    $fakeBalance = number_format(random_int(100, 50000) / 100, 2);

    // Log the balance check
    $stmt = $db->prepare(
        "INSERT INTO transactions (user_id, mfs_provider, type, amount, recipient, status, reference) VALUES (?, ?, 'balance', 0, NULL, 'success', ?)"
    );
    $stmt->bind_param('iss', $userId, $mfsSlug, $reference);
    $stmt->execute();
    $stmt->close();

    jsonResponse(true, $provider['name'] . ' ব্যালেন্স সফলভাবে চেক হয়েছে।', [
        'balance'   => $fakeBalance,
        'reference' => $reference,
    ]);
}

// Store transaction
$stmt = $db->prepare(
    "INSERT INTO transactions (user_id, mfs_provider, type, amount, recipient, status, reference) VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('issdsss', $userId, $mfsSlug, $type, $amount, $recipient, $status, $reference);
if (!$stmt->execute()) {
    $stmt->close();
    jsonResponse(false, 'লেনদেন সংরক্ষণ ব্যর্থ হয়েছে।');
}
$stmt->close();

$typeLabels = [
    'send'     => 'সেন্ড মানি',
    'cashout'  => 'ক্যাশ আউট',
    'recharge' => 'মোবাইল রিচার্জ',
    'payment'  => 'পেমেন্ট',
];

if ($status === 'success') {
    $msg = $provider['name'] . ' দিয়ে ' . ($typeLabels[$type] ?? $type) . ' সফল হয়েছে। পরিমাণ: ৳' . number_format($amount, 2);
    jsonResponse(true, $msg, [
        'reference' => $reference,
        'amount'    => number_format($amount, 2),
        'status'    => 'success',
    ]);
} else {
    jsonResponse(false, 'লেনদেন ব্যর্থ হয়েছে। পুনরায় চেষ্টা করুন।', ['reference' => $reference]);
}
