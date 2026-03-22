<?php
/**
 * Admin Logout
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
logoutAdmin();
