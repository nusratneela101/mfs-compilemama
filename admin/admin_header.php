<?php
/**
 * Admin Shared Header Include
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

startSecureSession();
requireAdmin();

$csrfToken    = generateCSRFToken();
$adminUser    = $_SESSION['admin_username'] ?? 'Admin';
if (!isset($pageTitle)) $pageTitle = 'Admin';

$_siteName    = getSetting('site_name', SITE_NAME);
$_themeColor  = getSetting('theme_color', '#E2136E');
$_favicon     = getSetting('site_favicon', '');

$navItems = [
    ['href'=>'/admin/index.php',         'icon'=>'fas fa-tachometer-alt', 'label'=>'Dashboard'],
    ['href'=>'/admin/users.php',         'icon'=>'fas fa-users',           'label'=>'Users'],
    ['href'=>'/admin/wallets.php',       'icon'=>'fas fa-wallet',          'label'=>'Wallets'],
    ['href'=>'/admin/subscriptions.php', 'icon'=>'fas fa-crown',           'label'=>'Subscriptions'],
    ['href'=>'/admin/payments.php',      'icon'=>'fas fa-money-bill-wave', 'label'=>'Payments'],
    ['href'=>'/admin/manage-admins.php', 'icon'=>'fas fa-user-shield',     'label'=>'Manage Admins'],
    ['href'=>'/admin/settings.php',      'icon'=>'fas fa-cog',             'label'=>'Site Settings'],
    ['href'=>'/admin/content.php',       'icon'=>'fas fa-paint-brush',     'label'=>'Content'],
    ['href'=>'/admin/profile.php',       'icon'=>'fas fa-id-badge',        'label'=>'My Profile'],
];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= sanitize($pageTitle) ?> — <?= sanitize($_siteName) ?> Admin</title>
    <?php if ($_favicon): ?>
    <link rel="icon" href="<?= sanitize($_favicon) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <style>
        body { font-family:'Inter',sans-serif; }
        .admin-brand { padding:20px;border-bottom:1px solid rgba(255,255,255,.1); }
        :root { --admin-theme: <?= sanitize($_themeColor) ?>; }
    </style>
</head>
<body style="background:#f5f5f5">
<div class="admin-layout">

<!-- Sidebar -->
<nav class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand text-white px-4 py-3">
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:1.8rem">💳</span>
            <div>
                <div class="fw-bold" style="font-size:.95rem"><?= sanitize($_siteName) ?></div>
                <div style="font-size:.7rem;opacity:.6">Admin Panel</div>
            </div>
        </div>
    </div>
    <ul class="nav flex-column mt-2">
        <?php foreach ($navItems as $item):
            $active = basename($item['href']) === $currentPage ? 'active' : '';
        ?>
        <li class="nav-item">
            <a class="nav-link <?= $active ?>" href="<?= $item['href'] ?>">
                <i class="<?= $item['icon'] ?> me-2 fa-fw"></i><?= $item['label'] ?>
            </a>
        </li>
        <?php endforeach; ?>
        <li class="nav-item mt-auto" style="position:absolute;bottom:20px;width:100%">
            <a class="nav-link text-danger" href="/admin/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
            <a class="nav-link text-white-50" href="/" target="_blank">
                <i class="fas fa-external-link-alt me-2"></i>View Site
            </a>
        </li>
    </ul>
</nav>

<!-- Main Content -->
<div class="admin-content">
    <!-- Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-3 shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0 fw-bold"><?= sanitize($pageTitle) ?></h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary">👤 <?= sanitize($adminUser) ?></span>
            <a href="/admin/profile.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fas fa-id-badge me-1"></i>Profile
            </a>
            <a href="/admin/logout.php" class="btn btn-sm btn-outline-danger rounded-pill">Logout</a>
        </div>
    </div>

