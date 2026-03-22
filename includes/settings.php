<?php
/**
 * Site Settings Helper
 * MFS Compilemama
 *
 * Loads settings and content from the database, falling back to
 * config.php constants when the tables don't exist yet.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Return all site settings as an associative array [key => value].
 * Results are cached in a static variable for the request lifetime.
 */
function getSiteSettings(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'site_name'          => SITE_NAME,
        'site_name_bn'       => SITE_NAME_BN,
        'site_description'   => 'বাংলাদেশের #১ MFS পোর্টাল',
        'site_logo'          => '',
        'site_favicon'       => '',
        'theme_color'        => '#E2136E',
        'footer_text'        => '© ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.',
        'contact_email'      => 'admin@mfscompilemama.com',
        'contact_phone'      => '+880 1XXXXXXXXX',
        'facebook_url'       => '',
        'whatsapp_number'    => '',
        'telegram_url'       => '',
        'subscription_price' => (string)SUB_AMOUNT,
        'currency_symbol'    => '৳',
    ];

    try {
        $db  = getDB();
        $res = @$db->query("SELECT setting_key, setting_value FROM site_settings");
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as $row) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (\Throwable $e) {
        // Table may not exist yet — return defaults silently
    }

    $cache = $defaults;
    return $cache;
}

/**
 * Get a single site setting value.
 */
function getSetting(string $key, string $default = ''): string {
    $settings = getSiteSettings();
    return $settings[$key] ?? $default;
}

/**
 * Save (upsert) a single setting to the database.
 */
function saveSetting(string $key, string $value): bool {
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO site_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP"
    );
    if (!$stmt) return false;
    $stmt->bind_param('sss', $key, $value, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Return all content rows for a given section as [content_key => content_value].
 */
function getSiteContent(string $section): array {
    static $contentCache = [];
    if (isset($contentCache[$section])) {
        return $contentCache[$section];
    }

    $result = [];
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT content_key, content_value FROM site_content WHERE section = ?");
        if ($stmt) {
            $stmt->bind_param('s', $section);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            foreach ($rows as $row) {
                $result[$row['content_key']] = $row['content_value'];
            }
        }
    } catch (\Throwable $e) {
        // Table may not exist yet
    }

    $contentCache[$section] = $result;
    return $result;
}

/**
 * Get a single content value.
 */
function getContent(string $section, string $key, string $default = ''): string {
    $content = getSiteContent($section);
    return $content[$key] ?? $default;
}

/**
 * Save (upsert) a single content item.
 */
function saveContent(string $section, string $key, string $value): bool {
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO site_content (section, content_key, content_value)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE content_value = ?, updated_at = CURRENT_TIMESTAMP"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ssss', $section, $key, $value, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
