-- MFS Compilemama — Database Migration v2
-- Adds site_settings and site_content tables

-- Site Settings table
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('text','textarea','image','color','number') NOT NULL DEFAULT 'text',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site Content table
CREATE TABLE IF NOT EXISTS `site_content` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `section` VARCHAR(100) NOT NULL,
    `content_key` VARCHAR(100) NOT NULL,
    `content_value` TEXT DEFAULT NULL,
    `content_type` ENUM('text','textarea','html','image') NOT NULL DEFAULT 'text',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_section_key` (`section`, `content_key`),
    INDEX `idx_section` (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings seed data
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('site_name',          'MFS Compilemama',                           'text'),
('site_name_bn',       'এমএফএস কম্পাইলমামা',                       'text'),
('site_description',   'বাংলাদেশের #১ MFS পোর্টাল',               'text'),
('site_logo',          '',                                           'image'),
('site_favicon',       '',                                           'image'),
('theme_color',        '#E2136E',                                    'color'),
('footer_text',        '© 2024 MFS Compilemama. All rights reserved.','text'),
('contact_email',      'admin@mfscompilemama.com',                   'text'),
('contact_phone',      '+880 1XXXXXXXXX',                            'text'),
('facebook_url',       '',                                           'text'),
('whatsapp_number',    '',                                           'text'),
('telegram_url',       '',                                           'text'),
('subscription_price', '150',                                        'number'),
('currency_symbol',    '৳',                                          'text')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Default content seed data
INSERT INTO `site_content` (`section`, `content_key`, `content_value`, `content_type`) VALUES
('hero', 'title',       'MFS Compilemama',                                                                                                                                 'text'),
('hero', 'subtitle',    'সব MFS একটি প্ল্যাটফর্মে',                                                                                                                       'text'),
('hero', 'description', 'বাংলাদেশের সকল মোবাইল ফিনান্সিয়াল সার্ভিস — bKash, Nagad, Rocket এবং আরও ১০টি — একটি সুন্দর, নিরাপদ এবং সহজ প্ল্যাটফর্মে ব্যবহার করুন। মাত্র ৳150/মাসে সম্পূর্ণ অ্যাক্সেস পান।', 'textarea'),
('hero', 'image',       '',                                                                                                                                                 'image'),
('about','title',       'আমাদের সম্পর্কে',                                                                                                                                 'text'),
('about','description', 'MFS Compilemama বাংলাদেশের প্রথম এবং একমাত্র প্ল্যাটফর্ম যেখানে আপনি সব MFS সেবা একসাথে পাবেন।',                                               'textarea')
ON DUPLICATE KEY UPDATE section = section;
