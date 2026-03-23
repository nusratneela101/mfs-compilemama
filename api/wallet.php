<?php
/**
 * Wallet REST API
 * MFS Compilemama
 *
 * Endpoints:
 *   GET  ?action=balance       — Get wallet balance
 *   GET  ?action=transactions  — Get transaction history
 *   POST ?action=add           — Add money
 *   POST ?action=withdraw      — Withdraw
 *   POST ?action=transfer      — Transfer
 *   POST ?action=set-pin       — Set wallet PIN
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wallet.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');

// Authentication check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'অনুগ্রহ করে লগইন করুন।']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Rate limiting: 30 API calls per minute per user
if (!rateLimitCheck('wallet_api_' . $userId, 'api', RATE_LIMIT_API_PER_MINUTE, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'অনেক বেশি অনুরোধ। কিছুক্ষণ পর চেষ্টা করুন।']);
    exit;
}

// CSRF check for state-changing methods
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf = $body['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF যাচাই ব্যর্থ হয়েছে।']);
        exit;
    }
}

switch ($action) {

    // -------------------------------------------------------
    // GET balance
    // -------------------------------------------------------
    case 'balance':
        if ($method !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
        $wallet = getOrCreateWallet($userId);
        echo json_encode([
            'success'   => true,
            'balance'   => (float)$wallet['balance'],
            'currency'  => 'BDT',
            'status'    => $wallet['status'],
            'free_limit_used'      => (float)$wallet['free_limit_used'],
            'free_limit_remaining' => max(0, WALLET_FREE_LIMIT - (float)$wallet['free_limit_used']),
        ]);
        break;

    // -------------------------------------------------------
    // GET transactions
    // -------------------------------------------------------
    case 'transactions':
        if ($method !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
        $limit  = min(100, max(1, (int)($_GET['limit']  ?? 20)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $type   = in_array($_GET['type'] ?? '', ['add_money','withdraw','transfer_in','transfer_out','fee']) ? ($_GET['type'] ?? '') : '';
        $result = getWalletTransactions($userId, $limit, $offset, $type);
        echo json_encode([
            'success' => true,
            'total'   => $result['total'],
            'rows'    => $result['rows'],
        ]);
        break;

    // -------------------------------------------------------
    // POST add
    // -------------------------------------------------------
    case 'add':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
        $body        = json_decode(file_get_contents('php://input'), true) ?? [];
        $mfsProvider = sanitize($body['mfs_provider'] ?? '');
        $mfsAccount  = trim($body['mfs_account'] ?? '');
        $amount      = (float)($body['amount'] ?? 0);
        $pin         = $body['wallet_pin'] ?? '';

        if (!getMFSProvider($mfsProvider)) { echo json_encode(['success'=>false,'message'=>'অবৈধ MFS প্রদানকারী।']); exit; }
        if (!validateBDPhone($mfsAccount)) { echo json_encode(['success'=>false,'message'=>'অবৈধ মোবাইল নম্বর।']); exit; }
        if (!verifyWalletPin($userId, $pin)) { echo json_encode(['success'=>false,'message'=>'PIN সঠিক নয়।']); exit; }

        $provider = getMFSProvider($mfsProvider);
        $result   = addMoney($userId, $amount, $provider['name'], $mfsAccount);
        echo json_encode($result);
        break;

    // -------------------------------------------------------
    // POST withdraw
    // -------------------------------------------------------
    case 'withdraw':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
        $body        = json_decode(file_get_contents('php://input'), true) ?? [];
        $mfsProvider = sanitize($body['mfs_provider'] ?? '');
        $mfsAccount  = trim($body['mfs_account'] ?? '');
        $amount      = (float)($body['amount'] ?? 0);
        $pin         = $body['wallet_pin'] ?? '';

        if (!getMFSProvider($mfsProvider)) { echo json_encode(['success'=>false,'message'=>'অবৈধ MFS প্রদানকারী।']); exit; }
        if (!validateBDPhone($mfsAccount)) { echo json_encode(['success'=>false,'message'=>'অবৈধ মোবাইল নম্বর।']); exit; }
        if (!verifyWalletPin($userId, $pin)) { echo json_encode(['success'=>false,'message'=>'PIN সঠিক নয়।']); exit; }

        $provider = getMFSProvider($mfsProvider);
        $result   = withdrawMoney($userId, $amount, $provider['name'], $mfsAccount);
        echo json_encode($result);
        break;

    // -------------------------------------------------------
    // POST transfer
    // -------------------------------------------------------
    case 'transfer':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $toPhone   = trim($body['recipient_phone'] ?? '');
        $amount    = (float)($body['amount'] ?? 0);
        $pin       = $body['wallet_pin'] ?? '';

        if (!validateBDPhone($toPhone)) { echo json_encode(['success'=>false,'message'=>'অবৈধ প্রাপক নম্বর।']); exit; }
        if (!verifyWalletPin($userId, $pin)) { echo json_encode(['success'=>false,'message'=>'PIN সঠিক নয়।']); exit; }

        $recipient = findUserByPhone($toPhone);
        if (!$recipient) { echo json_encode(['success'=>false,'message'=>'প্রাপক পাওয়া যায়নি।']); exit; }

        $result = transferMoney($userId, (int)$recipient['id'], $amount);
        echo json_encode($result);
        break;

    // -------------------------------------------------------
    // POST set-pin
    // -------------------------------------------------------
    case 'set-pin':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $pin     = $body['pin'] ?? '';
        $pinC    = $body['pin_confirm'] ?? '';
        $oldPin  = $body['old_pin'] ?? '';

        if (!ctype_digit($pin) || strlen($pin) < 4 || strlen($pin) > 6) {
            echo json_encode(['success'=>false,'message'=>'PIN অবশ্যই ৪-৬ সংখ্যার হতে হবে।']); exit;
        }
        if ($pin !== $pinC) {
            echo json_encode(['success'=>false,'message'=>'PIN মিলছে না।']); exit;
        }
        if (walletHasPin($userId) && !verifyWalletPin($userId, $oldPin)) {
            echo json_encode(['success'=>false,'message'=>'বর্তমান PIN সঠিক নয়।']); exit;
        }

        $ok = setWalletPin($userId, $pin);
        echo json_encode(['success'=>$ok,'message' => $ok ? 'PIN সেট হয়েছে।' : 'PIN সেট করতে সমস্যা হয়েছে।']);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'অবৈধ action।']);
        break;
}
