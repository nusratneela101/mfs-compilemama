<?php
/**
 * Admin — Content & Image Management
 * MFS Compilemama
 */

$pageTitle = 'Content Management';
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/upload.php';

$db  = getDB();
$msg = '';
$err = '';

// ── Save hero/about text content ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_content') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $fields = [
            ['hero',  'title'],
            ['hero',  'subtitle'],
            ['hero',  'description'],
            ['about', 'title'],
            ['about', 'description'],
        ];
        foreach ($fields as [$sec, $key]) {
            $val = trim($_POST["{$sec}_{$key}"] ?? '');
            saveContent($sec, $key, $val);
        }

        // Hero image upload
        if (!empty($_FILES['hero_image']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/site';
            $result    = handleImageUpload($_FILES['hero_image'], $uploadDir, 'hero');
            if ($result['ok']) {
                $old = getContent('hero', 'image');
                if ($old) deleteUploadedFile($old);
                saveContent('hero', 'image', $result['path']);
            } else {
                $err = 'Hero image upload failed: ' . $result['error'];
            }
        }

        if (!$err) $msg = 'Content saved successfully.';
    }
}

// ── Save MFS provider icon ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_provider_icon') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            $err = 'Invalid provider.';
        } elseif (empty($_FILES['provider_icon']['name'])) {
            $err = 'No icon file selected.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/logos';
            $result    = handleImageUpload($_FILES['provider_icon'], $uploadDir, 'mfs_' . $providerId);
            if ($result['ok']) {
                // Delete old icon if it's an uploaded file
                $row  = $db->prepare("SELECT icon FROM mfs_providers WHERE id = ?");
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
                $msg = 'Provider icon updated.';
            } else {
                $err = 'Icon upload failed: ' . $result['error'];
            }
        }
    }
}

// ── Save provider emoji icon fallback ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_provider_emoji') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $emoji      = trim($_POST['emoji'] ?? '');
        if ($providerId > 0 && mb_strlen($emoji) > 0) {
            $upd = $db->prepare("UPDATE mfs_providers SET icon = ? WHERE id = ?");
            $upd->bind_param('si', $emoji, $providerId);
            $upd->execute();
            $upd->close();
            $msg = 'Provider icon updated.';
        }
    }
}

// Fetch data
$heroContent  = getSiteContent('hero');
$aboutContent = getSiteContent('about');
$providers    = $db->query("SELECT id, name, name_bn, slug, icon FROM mfs_providers ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= sanitize($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($err): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Hero & About Content -->
<div class="admin-stat-card border-left-0 mb-4">
    <h5 class="fw-bold mb-4"><i class="fas fa-home me-2 text-primary"></i>Homepage Content</h5>
    <form method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="save_content">

        <h6 class="fw-semibold text-muted mb-3 border-bottom pb-2">🦸 Hero Section</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Hero Title</label>
                <input type="text" name="hero_title" class="form-control"
                       value="<?= sanitize($heroContent['title'] ?? '') ?>" maxlength="150">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Hero Subtitle</label>
                <input type="text" name="hero_subtitle" class="form-control"
                       value="<?= sanitize($heroContent['subtitle'] ?? '') ?>" maxlength="255">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Hero Description</label>
                <textarea name="hero_description" class="form-control" rows="3"><?= sanitize($heroContent['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Hero Background Image</label>
                <?php if (!empty($heroContent['image'])): ?>
                    <div class="mb-2">
                        <img src="<?= sanitize($heroContent['image']) ?>" alt="Hero image"
                             style="max-height:120px;max-width:320px;border-radius:8px;border:1px solid #ddd">
                        <div class="text-muted small mt-1">Current hero image</div>
                    </div>
                <?php endif; ?>
                <input type="file" name="hero_image" class="form-control"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       onchange="previewImage(this,'heroImgPreview')">
                <img id="heroImgPreview" src="" alt="" style="display:none;max-height:120px;margin-top:8px;border-radius:8px">
                <div class="form-text">JPG, PNG, GIF, WEBP — max 2 MB. Leave empty to keep current.</div>
            </div>
        </div>

        <h6 class="fw-semibold text-muted mb-3 border-bottom pb-2">ℹ️ About Section</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-semibold">About Title</label>
                <input type="text" name="about_title" class="form-control"
                       value="<?= sanitize($aboutContent['title'] ?? '') ?>" maxlength="150">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">About Description</label>
                <textarea name="about_description" class="form-control" rows="3"><?= sanitize($aboutContent['description'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save me-1"></i>Save Content
        </button>
    </form>
</div>

<!-- MFS Provider Icons -->
<div class="admin-stat-card border-left-0">
    <h5 class="fw-bold mb-4"><i class="fas fa-mobile-alt me-2 text-success"></i>MFS Provider Icons</h5>
    <p class="text-muted small mb-4">Upload real logo images for each MFS company to replace emoji icons. Recommended: PNG with transparent background, 200×200 px.</p>

    <div class="row g-3">
        <?php foreach ($providers as $prov): ?>
        <div class="col-md-6 col-lg-4">
            <div class="border rounded-3 p-3 h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if (!empty($prov['icon']) && str_starts_with($prov['icon'], '/')): ?>
                        <img src="<?= sanitize($prov['icon']) ?>" alt="<?= sanitize($prov['name']) ?>"
                             style="width:48px;height:48px;object-fit:contain;border-radius:8px;border:1px solid #eee">
                    <?php else: ?>
                        <span style="font-size:2.5rem;line-height:1"><?= sanitize($prov['icon'] ?? '💳') ?></span>
                    <?php endif; ?>
                    <div>
                        <div class="fw-bold"><?= sanitize($prov['name']) ?></div>
                        <div class="text-muted small"><?= sanitize($prov['name_bn']) ?></div>
                    </div>
                </div>

                <!-- Upload image -->
                <form method="POST" enctype="multipart/form-data" class="mb-2" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_provider_icon">
                    <input type="hidden" name="provider_id" value="<?= (int)$prov['id'] ?>">
                    <div class="input-group input-group-sm">
                        <input type="file" name="provider_icon" class="form-control form-control-sm"
                               accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="fas fa-upload"></i>
                        </button>
                    </div>
                    <div class="form-text">Upload PNG/JPG logo</div>
                </form>

                <!-- Or set emoji -->
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_provider_emoji">
                    <input type="hidden" name="provider_id" value="<?= (int)$prov['id'] ?>">
                    <div class="input-group input-group-sm">
                        <input type="text" name="emoji" class="form-control form-control-sm"
                               value="<?= (!str_starts_with($prov['icon'] ?? '', '/')) ? sanitize($prov['icon'] ?? '') : '' ?>"
                               maxlength="10" placeholder="emoji">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Set</button>
                    </div>
                    <div class="form-text">Or use an emoji</div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
