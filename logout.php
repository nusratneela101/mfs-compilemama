<?php
/**
 * Logout Handler — MFS Compilemama
 */

require_once __DIR__ . '/includes/auth.php';
startSecureSession();
logout();
