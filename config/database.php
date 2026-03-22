<?php
/**
 * Database Configuration
 * MFS Compilemama
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mfs_compilemama');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log('DB Connection failed: ' . $conn->connect_error);
            if (defined('API_REQUEST') && API_REQUEST) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database connection failed']);
                exit;
            }
            die('<h1>Service Unavailable</h1><p>Database connection failed. Please try again later.</p>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
