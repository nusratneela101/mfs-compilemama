<?php
/**
 * Authentication Middleware
 * MFS Compilemama
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $httpOnly = true;
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => $httpOnly,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Regenerate if session is old (prevent fixation)
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_phone']);
}

function isAdminLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db   = getDB();
    $id   = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id, phone, name, email, status, created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . $redirect . ($back ? '?redirect=' . $back : ''));
        exit;
    }
}

function requireSubscription(): void {
    requireLogin();
    require_once __DIR__ . '/functions.php';
    $sub = getSubscriptionStatus((int)$_SESSION['user_id']);
    if (!$sub) {
        header('Location: /subscribe.php?msg=subscription_required');
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function login(array $user): void {
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_phone'] = $user['phone'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['login_time'] = time();
}

function loginAdmin(array $admin): void {
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_login']    = time();

    // Update last_login
    $db   = getDB();
    $now  = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE admin_users SET last_login = ? WHERE id = ?");
    $stmt->bind_param('si', $now, $admin['id']);
    $stmt->execute();
    $stmt->close();
}

function logout(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: /login.php?msg=logged_out');
    exit;
}

function logoutAdmin(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
    header('Location: /admin/login.php?msg=logged_out');
    exit;
}
