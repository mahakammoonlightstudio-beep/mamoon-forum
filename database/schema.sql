-- ============================================================
-- Mamoon Forum — skema database (install baru)
-- MariaDB 10.4+ / MySQL 5.7+  |  Charset utf8mb4
--
-- Urutan tabel penting: users dibuat SEBELUM threads & posts
-- karena kedua tabel itu punya foreign key ke users.
-- ============================================================

-- ------------------------------------------------------------
-- Kategori forum
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon`        VARCHAR(50) DEFAULT NULL,
  `color`       VARCHAR(7) DEFAULT '#648c8c',
  `post_count`  INT DEFAULT 0,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- User (opsional — auth hybrid)
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50) NOT NULL,
  `email`         VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar_url`    VARCHAR(512) DEFAULT NULL,
  `role`          ENUM('user','moderator','admin') DEFAULT 'user',
  `is_active`     TINYINT(1) DEFAULT 1,
  `reputation`    INT DEFAULT 0,
  `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Thread (topik diskusi)
-- ------------------------------------------------------------
CREATE TABLE `threads` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255) NOT NULL,
  `content`     TEXT NOT NULL,
  `image_path`  VARCHAR(500) DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  `user_id`     INT DEFAULT NULL,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `bump_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `edited_at`   TIMESTAMP NULL DEFAULT NULL,
  `sticky`      TINYINT(1) DEFAULT 0,
  `locked`      TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_bump_at` (`bump_at`),
  KEY `idx_category` (`category_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_threads_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_threads_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Balasan (posts)
-- ------------------------------------------------------------
CREATE TABLE `posts` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `thread_id`  INT NOT NULL,
  `user_id`    INT DEFAULT NULL,
  `content`    TEXT NOT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `edited_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_thread_id` (`thread_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_posts_thread`
    FOREIGN KEY (`thread_id`) REFERENCES `threads` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_posts_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Vote thread & balasan (satu vote per user per target)
-- ------------------------------------------------------------
CREATE TABLE `votes` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `target_type` ENUM('thread','post','comment') NOT NULL,
  `target_id`   INT NOT NULL,
  `vote_type`   ENUM('up','down') NOT NULL,
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`user_id`, `target_type`, `target_id`),
  KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Rate limit anti-spam
-- ------------------------------------------------------------
CREATE TABLE `rate_limits` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `ip_address`    VARCHAR(45) NOT NULL,
  `action_type`   VARCHAR(50) NOT NULL,
  `attempt_count` INT DEFAULT 1,
  `window_start`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rate_limit` (`ip_address`, `action_type`),
  KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pengaturan situs
-- ------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Data awal
-- ------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `description`, `icon`, `color`) VALUES
('Umum',      'umum',    'Diskusi bebas tentang apa saja',            'fa-comments',       '#4a4c76'),
('Teknologi', 'teknologi','Programming, gadget, AI, dan tech lainnya', 'fa-microchip',      '#648c8c'),
('Gaming',    'gaming',  'Game discussion, walkthrough, esports',     'fa-gamepad',        '#e74c3c'),
('Hiburan',   'hiburan', 'Film, musik, anime, meme',                  'fa-film',           '#9b59b6'),
('Bantu Saya','bantuan', 'Tanya jawab dan cari solusi',               'fa-question-circle','#f39c12');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name',        'Mamoon Forum'),
('site_description', 'Tempat Diskusi Santai'),
('posts_per_page',   '15'),
('enable_registration', '1');
