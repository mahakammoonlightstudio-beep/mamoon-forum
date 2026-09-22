-- ============================================================
-- Mamoon Forum — UPGRADE database lama (jalankan SEKALI di phpMyAdmin)
--
-- Untuk database yang sudah berjalan (dari dump versi lama).
-- Aman untuk data yang ada: hanya menambah kolom & mengubah charset.
--
-- Cara: phpMyAdmin > pilih database > tab SQL > paste > Kirim.
-- ============================================================

-- 1. Samakan charset thread & balasan (dari latin1 ke utf8mb4)
ALTER TABLE `threads` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `posts`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Tambah kolom kategori di threads
ALTER TABLE `threads`
  ADD COLUMN `category_id` INT NULL DEFAULT NULL AFTER `image_path`,
  ADD KEY `idx_category` (`category_id`);

-- 3. Hubungkan ke tabel categories (opsional tapi disarankan)
ALTER TABLE `threads`
  ADD CONSTRAINT `fk_threads_category`
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
  ON DELETE SET NULL;
