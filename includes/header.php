<?php
/**
 * Global Header Include
 * MFS Compilemama
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

startSecureSession();
$csrfToken   = generateCSRFToken();
$currentUser = getCurrentUser();
$isLogged    = isLoggedIn();

// Load dynamic settings with fallback to config constants
$_siteName   = getSetting('site_name',    SITE_NAME);
$_siteNameBn = getSetting('site_name_bn', SITE_NAME_BN);
$_themeColor = getSetting('theme_color',  '#E2136E');
$_favicon    = getSetting('site_favicon', '');
$_logo       = getSetting('site_logo',    '');

// Page title default
if (!isset($pageTitle)) $pageTitle = $_siteName;
if (!isset($bodyClass)) $bodyClass = '';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize(getSetting('site_description', 'Bangladesh\'s premier multi-MFS portal')) ?>">
    <meta name="theme-color" content="<?= sanitize($_themeColor) ?>">
    <title><?= sanitize($pageTitle) ?> — <?= sanitize($_siteName) ?></title>
    <?php if ($_favicon): ?>
    <link rel="icon" href="<?= sanitize($_favicon) ?>">
    <?php endif; ?>

    <!-- Google Fonts: Hind Siliguri for Bengali -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">

    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <?php if ($_themeColor !== '#E2136E'): ?>
    <style>:root { --theme-color: <?= sanitize($_themeColor) ?>; }</style>
    <?php endif; ?>
</head>
<body class="<?= sanitize($bodyClass) ?>">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark mfs-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <?php if ($_logo): ?>
                <img src="<?= sanitize($_logo) ?>" alt="<?= sanitize($_siteName) ?>" style="max-height:40px;width:auto">
            <?php else: ?>
                <span class="brand-icon">💳</span>
            <?php endif; ?>
            <div>
                <span class="brand-name"><?= sanitize($_siteName) ?></span>
                <span class="brand-name-bn d-block"><?= sanitize($_siteNameBn) ?></span>
            </div>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <li class="nav-item"><a class="nav-link" href="/"><i class="fas fa-home me-1"></i>হোম</a></li>
                <?php if ($isLogged): ?>
                    <li class="nav-item"><a class="nav-link" href="/dashboard.php"><i class="fas fa-th-large me-1"></i>ড্যাশবোর্ড</a></li>
                    <li class="nav-item"><a class="nav-link" href="/mfs-portal.php"><i class="fas fa-mobile-alt me-1"></i>MFS পোর্টাল</a></li>
                    <li class="nav-item"><a class="nav-link" href="/wallet.php"><i class="fas fa-wallet me-1"></i>💰 ওয়ালেট</a></li>
                    <li class="nav-item"><a class="nav-link" href="/transaction.php"><i class="fas fa-history me-1"></i>লেনদেন</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= sanitize($currentUser['name'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/profile.php"><i class="fas fa-user me-2"></i>প্রোফাইল</a></li>
                            <li><a class="dropdown-item" href="/subscribe.php"><i class="fas fa-crown me-2"></i>সাবস্ক্রিপশন</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>লগআউট</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/login.php"><i class="fas fa-sign-in-alt me-1"></i>লগইন</a></li>
                    <li class="nav-item">
                        <a class="btn btn-light text-primary fw-bold ms-2 px-3" href="/register.php">
                            <i class="fas fa-user-plus me-1"></i>রেজিস্টার
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
