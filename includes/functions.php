<?php
/**
 * Helper Functions
 * MFS Compilemama
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Format money in BDT
 */
function formatMoney(float $amount, bool $symbol = true): string {
    $formatted = number_format($amount, 2);
    return $symbol ? '৳' . $formatted : $formatted;
}

/**
 * Generate 6-digit OTP
 */
function generateOTP(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Send OTP via configured gateway
 */
function sendOTP(string $phone, string $code): bool {
    $message = "Your MFS Compilemama OTP is: {$code}. Valid for " . OTP_EXPIRY_MINUTES . " minutes. Do not share.";

    switch (SMS_GATEWAY) {
        case 'bulksmsbd':
            return sendViaBulkSMSBD($phone, $message);
        case 'smsq':
            return sendViaSMSQ($phone, $message);
        case 'twilio':
            return sendViaTwilio($phone, $message);
        case 'log':
        default:
            return logSMS($phone, $message);
    }
}

function sendViaBulkSMSBD(string $phone, string $message): bool {
    if (!BULKSMSBD_API_KEY) return logSMS($phone, $message);
    $url = 'http://bulksmsbd.net/api/smsapi';
    $params = http_build_query([
        'api_key'   => BULKSMSBD_API_KEY,
        'type'      => 'text',
        'number'    => $phone,
        'senderid'  => BULKSMSBD_SENDER_ID,
        'message'   => $message,
    ]);
    $result = @file_get_contents($url . '?' . $params);
    return $result !== false;
}

function sendViaSMSQ(string $phone, string $message): bool {
    if (!SMSQ_API_KEY) return logSMS($phone, $message);
    $url = 'https://smsq.com.bd/api/v1/send';
    $data = json_encode(['api_key' => SMSQ_API_KEY, 'to' => $phone, 'message' => $message]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $data,
        'timeout' => 10,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result !== false;
}

function sendViaTwilio(string $phone, string $message): bool {
    if (!TWILIO_SID || !TWILIO_TOKEN) return logSMS($phone, $message);
    $url  = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json";
    $data = http_build_query(['To' => '+880' . ltrim($phone, '0'), 'From' => TWILIO_FROM, 'Body' => $message]);
    $ctx  = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Authorization: Basic " . base64_encode(TWILIO_SID . ':' . TWILIO_TOKEN) . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 15,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result !== false;
}

function logSMS(string $phone, string $message): bool {
    $logDir = dirname(SMS_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    $entry = '[' . date('Y-m-d H:i:s') . '] TO:' . $phone . ' MSG:' . $message . PHP_EOL;
    return file_put_contents(SMS_LOG_FILE, $entry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Hash a PIN using bcrypt
 */
function hashPIN(string $pin): string {
    return password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a PIN against its hash
 */
function verifyPIN(string $pin, string $hash): bool {
    return password_verify($pin, $hash);
}

/**
 * Sanitize output to prevent XSS
 */
function sanitize(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate CSRF token and store in session
 */
function generateCSRFToken(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $stored = $_SESSION['csrf_token'] ?? '';
    return !empty($stored) && hash_equals($stored, $token);
}

/**
 * Check rate limit for an identifier+action
 * Returns true if within limit, false if exceeded
 */
function rateLimitCheck(string $identifier, string $action, int $maxAttempts, int $windowSeconds): bool {
    $db = getDB();
    $now = date('Y-m-d H:i:s');

    // Cleanup expired entries for this identifier+action
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ? AND reset_at < ?");
    $stmt->bind_param('sss', $identifier, $action, $now);
    $stmt->execute();
    $stmt->close();

    // Get current record
    $stmt = $db->prepare("SELECT id, attempts FROM rate_limits WHERE identifier = ? AND action = ?");
    $stmt->bind_param('ss', $identifier, $action);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $resetAt = date('Y-m-d H:i:s', time() + $windowSeconds);
        $stmt = $db->prepare("INSERT INTO rate_limits (identifier, action, attempts, reset_at) VALUES (?, ?, 1, ?)");
        $stmt->bind_param('sss', $identifier, $action, $resetAt);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    if ($row['attempts'] >= $maxAttempts) {
        return false;
    }

    $stmt = $db->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?");
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Format a date/timestamp
 */
function formatDate(string $datetime, string $format = 'd M Y, h:i A'): string {
    return date($format, strtotime($datetime));
}

/**
 * Get active subscription status for a user
 */
function getSubscriptionStatus(int $userId): ?array {
    $db  = getDB();
    $now = date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= ? ORDER BY end_date DESC LIMIT 1"
    );
    $stmt->bind_param('is', $userId, $now);
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $sub ?: null;
}

/**
 * Validate Bangladesh phone number
 */
function validateBDPhone(string $phone): bool {
    return (bool)preg_match('/^01[3-9]\d{8}$/', $phone);
}

/**
 * Generate a unique transaction reference
 */
function generateReference(): string {
    return 'TXN' . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Get all active MFS providers
 */
function getMFSProviders(): array {
    $db   = getDB();
    $res  = $db->query("SELECT * FROM mfs_providers WHERE status = 'active' ORDER BY sort_order ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Get MFS provider by slug
 */
function getMFSProvider(string $slug): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM mfs_providers WHERE slug = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Return a JSON response and exit
 */
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Redirect helper
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
