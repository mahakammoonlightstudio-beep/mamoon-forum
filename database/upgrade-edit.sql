-- ============================================================
-- Mamoon Forum — UPGRADE: fitur edit konten (jalankan SEKALI di phpMyAdmin)
--
-- Menambahkan kolom edited_at pada threads dan posts.
-- Aman untuk data yang sudah ada (kolom baru bernilai NULL).
--
-- Cara: phpMyAdmin > pilih database > tab SQL > paste > Kirim.
-- ============================================================

ALTER TABLE `threads`
  ADD COLUMN `edited_at` TIMESTAMP NULL DEFAULT NULL AFTER `bump_at`;

ALTER TABLE `posts`
  ADD COLUMN `edited_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`;
