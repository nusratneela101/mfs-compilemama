<?php
/**
 * Database Install Script
 * Run this once to set up the database
 * Access: http://yoursite.com/install.php
 * DELETE THIS FILE AFTER INSTALLATION!
 */

// Basic security: require a secret key
define('INSTALL_SECRET', 'mfs-install-2024');
if (($_GET['key'] ?? '') !== INSTALL_SECRET) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Provide ?key=' . INSTALL_SECRET . ' to proceed.</p>');
}

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'mfs_compilemama';

$conn = new mysqli($dbHost, $dbUser, $dbPass);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Read SQL file
$sql = file_get_contents(__DIR__ . '/sql/database.sql');
if (!$sql) {
    die('Cannot read SQL file.');
}

// Execute statements
$conn->multi_query($sql);
do {
    $conn->store_result();
} while ($conn->next_result());

if ($conn->errno) {
    die('SQL Error: ' . $conn->error);
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Install</title>';
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">';
echo '</head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-6">';
echo '<div class="card shadow"><div class="card-body text-center p-5">';
echo '<h1>✅ Installation Complete!</h1>';
echo '<p class="text-muted mt-3">Database <strong>' . htmlspecialchars($dbName) . '</strong> setup successfully.</p>';
echo '<hr>';
echo '<p><strong>Admin Login:</strong><br>Username: <code>admin</code><br>Password: <code>admin123</code></p>';
echo '<p class="text-danger"><strong>⚠️ IMPORTANT: Delete this file immediately!</strong><br><code>rm install.php</code></p>';
echo '<a href="/" class="btn btn-primary rounded-pill mt-3">Go to Homepage</a>';
echo '</div></div></div></div></div></body></html>';
