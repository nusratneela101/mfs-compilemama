<?php
/**
 * Admin — AJAX File Upload Handler
 * MFS Compilemama
 *
 * Handles AJAX image uploads and returns a JSON response.
 * POST parameters:
 *   csrf_token  - required
 *   upload_type - 'site_logo' | 'site_favicon' | 'hero_image' | 'provider_icon'
 *   provider_id - required when upload_type = 'provider_icon'
 *   file        - the uploaded file ($_FILES['file'])
 */

define('API_REQUEST', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/location.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/upload.php';

startSecureSession();
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required.']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$uploadType = $_POST['upload_type'] ?? '';
$allowed    = ['site_logo', 'site_favicon', 'hero_image', 'provider_icon'];

if (!in_array($uploadType, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Unknown upload type.']);
    exit;
}

if (empty($_FILES['file']['name'])) {
    echo json_encode(['success' => false, 'error' => 'No file provided.']);
    exit;
}

$db = getDB();

switch ($uploadType) {
    case 'site_logo':
        $dir    = __DIR__ . '/../uploads/site';
        $result = handleImageUpload($_FILES['file'], $dir, 'logo');
        if ($result['ok']) {
            $old = getSetting('site_logo');
            if ($old) deleteUploadedFile($old);
            saveSetting('site_logo', $result['path']);
        }
        break;

    case 'site_favicon':
        $dir    = __DIR__ . '/../uploads/site';
        $result = handleImageUpload($_FILES['file'], $dir, 'favicon');
        if ($result['ok']) {
            $old = getSetting('site_favicon');
            if ($old) deleteUploadedFile($old);
            saveSetting('site_favicon', $result['path']);
        }
        break;

    case 'hero_image':
        $dir    = __DIR__ . '/../uploads/site';
        $result = handleImageUpload($_FILES['file'], $dir, 'hero');
        if ($result['ok']) {
            $old = getContent('hero', 'image');
            if ($old) deleteUploadedFile($old);
            saveContent('hero', 'image', $result['path']);
        }
        break;

    case 'provider_icon':
        $providerId = (int)($_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            echo json_encode(['success' => false, 'error' => 'provider_id is required.']);
            exit;
        }
        $dir    = __DIR__ . '/../uploads/logos';
        $result = handleImageUpload($_FILES['file'], $dir, 'mfs_' . $providerId);
        if ($result['ok']) {
            $row = $db->prepare("SELECT icon FROM mfs_providers WHERE id = ?");
            $row->bind_param('i', $providerId);
            $row->execute();
            $old = $row->get_result()->fetch_assoc()['icon'] ?? '';
            $row->close();
            if ($old && str_starts_with($old, '/uploads/')) {
                deleteUploadedFile($old);
            }
            $upd = $db->prepare("UPDATE mfs_providers SET icon = ? WHERE id = ?");
            $upd->bind_param('si', $result['path'], $providerId);
            $upd->execute();
            $upd->close();
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unhandled upload type.']);
        exit;
}

echo json_encode([
    'success' => $result['ok'],
    'path'    => $result['path'] ?? '',
    'error'   => $result['error'] ?? '',
]);
