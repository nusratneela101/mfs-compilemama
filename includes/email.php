<?php
/**
 * Email Helper Functions
 * MFS Compilemama
 */

/**
 * Send verification email with a link to activate the account.
 */
function sendVerificationEmail(string $email, string $name, string $token): bool {
    $verifyUrl = SITE_URL . '/verify-email.php?token=' . rawurlencode($token);

    $subject = '=?UTF-8?B?' . base64_encode('MFS Compilemama - ইমেইল ভেরিফিকেশন') . '?=';

    $safeName     = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeUrl      = htmlspecialchars($verifyUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $expiry       = defined('VERIFICATION_EXPIRY_HOURS') ? VERIFICATION_EXPIRY_HOURS : 24;

    $htmlBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #E2136E; padding: 20px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>MFS Compilemama</h1>
        </div>
        <div style='padding: 30px; background: #ffffff;'>
            <h2 style='color: #333;'>স্বাগতম, {$safeName}!</h2>
            <p style='color: #555;'>আপনার অ্যাকাউন্ট ভেরিফাই করতে নিচের বাটনে ক্লিক করুন:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$safeUrl}'
                   style='background: #E2136E; color: white; padding: 15px 40px; text-decoration: none;
                          border-radius: 30px; font-size: 18px; display: inline-block;'>
                    ✅ ভেরিফাই করুন
                </a>
            </div>
            <p style='color: #555;'>অথবা এই লিংক কপি করে ব্রাউজারে পেস্ট করুন:</p>
            <p style='word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 5px; color: #333;'>
                {$safeUrl}
            </p>
            <p style='color: #999; font-size: 12px;'>এই লিংক {$expiry} ঘণ্টা পর্যন্ত বৈধ থাকবে।</p>
        </div>
        <div style='background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #999;'>
            &copy; MFS Compilemama. All rights reserved.
        </div>
    </div>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: MFS Compilemama <noreply@compilemama.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return mail($email, $subject, $htmlBody, $headers);
}
