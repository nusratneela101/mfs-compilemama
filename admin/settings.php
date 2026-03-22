<?php
/**
 * Admin — Site Settings Management
 * MFS Compilemama
 */

$pageTitle = 'Site Settings';
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/upload.php';

$db  = getDB();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid CSRF token.';
    } else {
        $textFields = [
            'site_name', 'site_name_bn', 'site_description', 'theme_color',
            'footer_text', 'contact_email', 'contact_phone',
            'facebook_url', 'whatsapp_number', 'telegram_url',
            'subscription_price', 'currency_symbol',
        ];

        foreach ($textFields as $field) {
            $value = trim($_POST[$field] ?? '');

            // Basic validation for specific fields
            if ($field === 'contact_email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $err = 'Invalid contact email address.';
                break;
            }
            if ($field === 'subscription_price' && $value !== '' && (!is_numeric($value) || (float)$value < 0)) {
                $err = 'Subscription price must be a non-negative number.';
                break;
            }
            if ($field === 'theme_color' && $value && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                $err = 'Theme color must be a valid hex color (e.g. #E2136E).';
                break;
            }

            saveSetting($field, $value);
        }

        if (!$err) {
            // Handle site_logo upload
            if (!empty($_FILES['site_logo']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/site';
                $result    = handleImageUpload($_FILES['site_logo'], $uploadDir, 'logo');
                if ($result['ok']) {
                    // Delete old logo
                    $old = getSetting('site_logo');
                    if ($old) deleteUploadedFile($old);
                    saveSetting('site_logo', $result['path']);
                } else {
                    $err = 'Logo upload failed: ' . $result['error'];
                }
            }

            // Handle site_favicon upload
            if (!$err && !empty($_FILES['site_favicon']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/site';
                $result    = handleImageUpload($_FILES['site_favicon'], $uploadDir, 'favicon');
                if ($result['ok']) {
                    $old = getSetting('site_favicon');
                    if ($old) deleteUploadedFile($old);
                    saveSetting('site_favicon', $result['path']);
                } else {
                    $err = 'Favicon upload failed: ' . $result['error'];
                }
            }
        }

        if (!$err) {
            $msg = 'Settings saved successfully.';
        }
    }
}

// Reload settings after save
$settings = getSiteSettings();
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

<form method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

    <div class="row g-4">

        <!-- General Settings -->
        <div class="col-lg-6">
            <div class="admin-stat-card border-left-0">
                <h5 class="fw-bold mb-4"><i class="fas fa-globe me-2 text-primary"></i>General Settings</h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Site Name (English)</label>
                    <input type="text" name="site_name" class="form-control"
                           value="<?= sanitize($settings['site_name']) ?>" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Site Name (Bengali)</label>
                    <input type="text" name="site_name_bn" class="form-control"
                           value="<?= sanitize($settings['site_name_bn']) ?>" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Site Description / Tagline</label>
                    <input type="text" name="site_description" class="form-control"
                           value="<?= sanitize($settings['site_description']) ?>" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Footer Text</label>
                    <input type="text" name="footer_text" class="form-control"
                           value="<?= sanitize($settings['footer_text']) ?>" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Theme Color</label>
                    <div class="input-group">
                        <input type="color" name="theme_color" class="form-control form-control-color"
                               value="<?= sanitize($settings['theme_color']) ?>"
                               style="max-width:60px" id="themeColorPicker">
                        <input type="text" id="themeColorText" class="form-control"
                               value="<?= sanitize($settings['theme_color']) ?>"
                               maxlength="7" placeholder="#E2136E" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="col-lg-6">
            <div class="admin-stat-card border-left-0">
                <h5 class="fw-bold mb-4"><i class="fas fa-images me-2 text-success"></i>Images</h5>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Site Logo</label>
                    <?php if ($settings['site_logo']): ?>
                        <div class="mb-2">
                            <img src="<?= sanitize($settings['site_logo']) ?>" alt="Current logo"
                                 style="max-height:60px;max-width:200px;border:1px solid #ddd;border-radius:6px;padding:4px">
                            <div class="text-muted small mt-1">Current logo</div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="site_logo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp"
                           onchange="previewImage(this,'logoPreview')">
                    <img id="logoPreview" src="" alt="" style="display:none;max-height:60px;margin-top:8px;border-radius:6px">
                    <div class="form-text">JPG, PNG, GIF, WEBP — max 2 MB. Leave empty to keep current.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Favicon</label>
                    <?php if ($settings['site_favicon']): ?>
                        <div class="mb-2">
                            <img src="<?= sanitize($settings['site_favicon']) ?>" alt="Current favicon"
                                 style="max-height:32px;max-width:32px;border:1px solid #ddd;border-radius:4px;padding:2px">
                            <div class="text-muted small mt-1">Current favicon</div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="site_favicon" class="form-control" accept="image/x-icon,image/png,image/gif"
                           onchange="previewImage(this,'faviconPreview')">
                    <img id="faviconPreview" src="" alt="" style="display:none;max-height:32px;margin-top:8px;border-radius:4px">
                    <div class="form-text">ICO, PNG, GIF — max 2 MB. Leave empty to keep current.</div>
                </div>
            </div>
        </div>

        <!-- Contact & Social -->
        <div class="col-lg-6">
            <div class="admin-stat-card border-left-0">
                <h5 class="fw-bold mb-4"><i class="fas fa-address-book me-2 text-info"></i>Contact &amp; Social</h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control"
                           value="<?= sanitize($settings['contact_email']) ?>" maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control"
                           value="<?= sanitize($settings['contact_phone']) ?>" maxlength="20">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="fab fa-facebook text-primary me-1"></i>Facebook URL</label>
                    <input type="url" name="facebook_url" class="form-control"
                           value="<?= sanitize($settings['facebook_url']) ?>" maxlength="255" placeholder="https://facebook.com/...">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Number</label>
                    <input type="text" name="whatsapp_number" class="form-control"
                           value="<?= sanitize($settings['whatsapp_number']) ?>" maxlength="20" placeholder="880XXXXXXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="fab fa-telegram text-info me-1"></i>Telegram URL</label>
                    <input type="url" name="telegram_url" class="form-control"
                           value="<?= sanitize($settings['telegram_url']) ?>" maxlength="255" placeholder="https://t.me/...">
                </div>
            </div>
        </div>

        <!-- Subscription Pricing -->
        <div class="col-lg-6">
            <div class="admin-stat-card border-left-0">
                <h5 class="fw-bold mb-4"><i class="fas fa-tag me-2 text-warning"></i>Subscription &amp; Pricing</h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Monthly Subscription Price</label>
                    <input type="number" name="subscription_price" class="form-control"
                           value="<?= sanitize($settings['subscription_price']) ?>" min="0" step="1" maxlength="10">
                    <div class="form-text">Current: <?= sanitize($settings['currency_symbol']) ?><?= sanitize($settings['subscription_price']) ?>/month</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control"
                           value="<?= sanitize($settings['currency_symbol']) ?>" maxlength="5">
                </div>
            </div>
        </div>

    </div><!-- end row -->

    <div class="mt-3 mb-4">
        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i class="fas fa-save me-2"></i>Save All Settings
        </button>
    </div>
</form>

<script>
// Sync color picker and text input
const picker = document.getElementById('themeColorPicker');
const text   = document.getElementById('themeColorText');
picker.addEventListener('input', () => text.value = picker.value);

// Image preview helper
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
