-- ============================================================
-- Mamoon Forum — UPGRADE: fitur voting (jalankan SEKALI di phpMyAdmin)
--
-- Untuk database yang sudah berjalan.
-- - Database baru        : tidak perlu (semua sudah ada di schema.sql)
-- - Database dari dump   : jalankan bagian 1, 2, dan 3
-- - Database TANPA votes : jalankan bagian 1 + 2, lalu 3
--
-- Cara: phpMyAdmin > pilih database > tab SQL > paste > Kirim.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Tambah 'thread' ke enum target_type (dump lama hanya post/comment)
--    (LEWATI bila tabel votes belum ada — langsung ke bagian 2)
-- ------------------------------------------------------------
ALTER TABLE `votes`
  MODIFY COLUMN `target_type` ENUM('thread','post','comment') NOT NULL;

-- ------------------------------------------------------------
-- 2. Buat tabel votes (LEWATI bila tabel sudah ada)
-- ------------------------------------------------------------
-- CREATE TABLE `votes` (
--   `id`          INT NOT NULL AUTO_INCREMENT,
--   `user_id`     INT NOT NULL,
--   `target_type` ENUM('thread','post','comment') NOT NULL,
--   `target_id`   INT NOT NULL,
--   `vote_type`   ENUM('up','down') NOT NULL,
--   `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
--   PRIMARY KEY (`id`),
--   UNIQUE KEY `unique_vote` (`user_id`, `target_type`, `target_id`),
--   KEY `idx_target` (`target_type`, `target_id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Kolom user_id di threads & posts (pembuat konten, untuk reputasi)
--    Aman: thread/balasan lama tetap dianggap anonim (user_id NULL).
-- ------------------------------------------------------------
ALTER TABLE `threads`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `category_id`,
  ADD KEY `idx_user` (`user_id`),
  ADD CONSTRAINT `fk_threads_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL;

ALTER TABLE `posts`
  ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `thread_id`,
  ADD KEY `idx_user` (`user_id`),
  ADD CONSTRAINT `fk_posts_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL;
