<?php
/**
 * Image Upload Helper
 * MFS Compilemama
 */

/**
 * Handle an image file upload.
 *
 * @param  array  $file       $_FILES element
 * @param  string $destDir    Absolute destination directory (must exist or be creatable)
 * @param  string $prefix     File name prefix (e.g. 'logo', 'favicon')
 * @return array{ok:bool, path:string, error:string}
 */
function handleImageUpload(array $file, string $destDir, string $prefix = 'img'): array {
    $maxBytes    = 2 * 1024 * 1024; // 2 MB
    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/webp'];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'gif', 'ico', 'webp'];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errMsg = match ($file['error'] ?? -1) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File size exceeds the allowed limit (2 MB).',
            UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
            default              => 'Upload error occurred.',
        };
        return ['ok' => false, 'path' => '', 'error' => $errMsg];
    }

    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'path' => '', 'error' => 'File size exceeds 2 MB limit.'];
    }

    // Validate MIME via finfo (more reliable than $_FILES['type'])
    $finfo    = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedMime, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'Only JPG, PNG, GIF, ICO and WEBP images are allowed.'];
    }

    // Map MIME to extension
    $extMap = [
        'image/jpeg'                  => 'jpg',
        'image/png'                   => 'png',
        'image/gif'                   => 'gif',
        'image/x-icon'                => 'ico',
        'image/vnd.microsoft.icon'    => 'ico',
        'image/webp'                  => 'webp',
    ];
    $ext = $extMap[$mimeType];

    // Also verify client-provided extension is acceptable
    $clientExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($clientExt, $allowedExt, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'File extension not allowed.'];
    }

    // Create dest directory if needed
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            return ['ok' => false, 'path' => '', 'error' => 'Could not create upload directory.'];
        }
    }

    $fileName = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'path' => '', 'error' => 'Failed to save uploaded file.'];
    }

    // Return path relative to document root for use in <img src="...">
    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $webPath   = str_replace('\\', '/', $destPath);
    if ($docRoot && str_starts_with($webPath, $docRoot)) {
        $webPath = substr($webPath, strlen($docRoot));
    }

    return ['ok' => true, 'path' => $webPath, 'error' => ''];
}

/**
 * Delete a previously uploaded file given its web path.
 */
function deleteUploadedFile(string $webPath): bool {
    if (empty($webPath)) return false;
    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $absPath  = $docRoot . '/' . ltrim($webPath, '/');
    $absPath  = realpath($absPath);
    if (!$absPath) return false;
    // Safety: only delete files inside the uploads directory
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if (!$uploadsDir || !str_starts_with($absPath, $uploadsDir)) return false;
    return unlink($absPath);
}
