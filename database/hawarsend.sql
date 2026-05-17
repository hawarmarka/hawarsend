-- Send Database Schema
-- Server-ready schema for Docker/Coolify
-- Encoding: UTF-8

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(255) NOT NULL,
  `username`        VARCHAR(80) DEFAULT NULL,
  `password`        VARCHAR(255) NOT NULL,
  `status`          ENUM('active','banned','pending') NOT NULL DEFAULT 'active',
  `remember_token`  VARCHAR(128) DEFAULT NULL,
  `remember_expires` DATETIME DEFAULT NULL,
  `last_login`      DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_remember` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`       VARCHAR(255) NOT NULL,
  `password`    VARCHAR(255) NOT NULL,
  `name`        VARCHAR(120) DEFAULT 'Admin',
  `last_login`  DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `uploads` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`           VARCHAR(64) NOT NULL,
  `user_id`         INT UNSIGNED DEFAULT NULL,
  `title`           VARCHAR(255) DEFAULT NULL,
  `password_hash`   VARCHAR(255) DEFAULT NULL,
  `expires_at`      DATETIME DEFAULT NULL,
  `download_limit`  INT UNSIGNED DEFAULT NULL,
  `download_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `total_size`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `ip_address`      VARCHAR(45) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_uploads_token` (`token`),
  KEY `idx_uploads_user` (`user_id`),
  KEY `idx_uploads_expires` (`expires_at`),
  CONSTRAINT `fk_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `upload_files` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `upload_id`     INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name`   VARCHAR(255) NOT NULL,
  `mime_type`     VARCHAR(127) DEFAULT NULL,
  `file_size`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_upload_files_upload` (`upload_id`),
  CONSTRAINT `fk_upload_files_upload` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `download_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `upload_id`   INT UNSIGNED NOT NULL,
  `file_id`     INT UNSIGNED DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_agent`  VARCHAR(512) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dl_upload` (`upload_id`),
  KEY `idx_dl_created` (`created_at`),
  CONSTRAINT `fk_dl_upload` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(100) NOT NULL,
  `value`       LONGTEXT DEFAULT NULL,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `upload_token`  VARCHAR(64) NOT NULL,
  `reason`        VARCHAR(100) NOT NULL,
  `details`       TEXT DEFAULT NULL,
  `reporter_email` VARCHAR(255) DEFAULT NULL,
  `reporter_ip`   VARCHAR(45) DEFAULT NULL,
  `status`        ENUM('open','resolved') NOT NULL DEFAULT 'open',
  `resolved_at`   DATETIME DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_token` (`upload_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(255) NOT NULL,
  `token`      VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_token` (`token`),
  KEY `idx_pr_email` (`email`),
  KEY `idx_pr_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`      VARCHAR(80) NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `details`     VARCHAR(512) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_action` (`action`),
  KEY `idx_al_ip` (`ip_address`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('site_name',            'Send'),
('site_description',     'Premium ve güvenli dosya paylaşım platformu'),
('site_keywords',        'send, dosya paylaşım, güvenli transfer, file sharing'),
('hero_title',           'Basit ve gizli dosya paylaşımı'),
('hero_description',     'Send ile dosyalarınızı güvenli bir bağlantı üzerinden paylaşın. Şifre ekleyin, süre belirleyin ve saniyeler içinde paylaşım linkinizi oluşturun.'),
('footer_text',          ''),
('allow_guest',          '1'),
('allow_register',       '1'),
('maintenance_mode',     '0'),
('max_file_size',        '30720'),
('default_expire',       '24'),
('blocked_extensions',   'php,phtml,phar,php3,php4,php5,php7,phps,exe,sh,bash,bat,cmd,com,vbs,ps1,jar,msi,dll,scr'),
('max_files_per_upload', '20'),
('smtp_host',            ''),
('smtp_port',            '587'),
('smtp_user',            ''),
('smtp_pass',            ''),
('smtp_from',            ''),
('smtp_from_name',       'Send'),
('custom_css',           ''),
('custom_js',            ''),
('header_code',          ''),
('footer_code',          ''),
('analytics_code',       ''),
('ad_top',               ''),
('ad_middle',            ''),
('ad_download',          ''),
('ad_footer',            ''),
('logo',                 ''),
('favicon',              '');

SET FOREIGN_KEY_CHECKS = 1;
