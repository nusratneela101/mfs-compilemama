<?php
/**
 * OTP Verification — Redirects to register.php
 * SMS OTP has been replaced by email verification.
 * This file is kept for backward compatibility.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

redirect('/register.php');
