-- ============================================================
-- Mamoon Forum — data demo untuk tes lokal (opsional)
--
-- Cara pakai (setelah import schema.sql):
--   mysql -u root -p nama_database < database/seed-demo.sql
--
-- Semua akun demo: password = "password123"
-- MENGHAPUS data lama di tabel utama (bukan untuk production!).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `votes`;
TRUNCATE TABLE `posts`;
TRUNCATE TABLE `threads`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Password semua akun: password123
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'mamoon', 'mamoon@demo.test', '$2y$12$sKod0EgmRxf8aXzVOl8kS.e36td0ApUaLnsmsI3Ywbe13cXB428zO', 'admin', NOW() - INTERVAL 30 DAY),
(2, 'budi',   'budi@demo.test',   '$2y$12$sKod0EgmRxf8aXzVOl8kS.e36td0ApUaLnsmsI3Ywbe13cXB428zO', 'user',  NOW() - INTERVAL 20 DAY),
(3, 'cito',   'cito@demo.test',   '$2y$12$sKod0EgmRxf8aXzVOl8kS.e36td0ApUaLnsmsI3Ywbe13cXB428zO', 'user',  NOW() - INTERVAL 15 DAY),
(4, 'dewi',   'dewi@demo.test',   '$2y$12$sKod0EgmRxf8aXzVOl8kS.e36td0ApUaLnsmsI3Ywbe13cXB428zO', 'user',  NOW() - INTERVAL 10 DAY),
(5, 'eko',    'eko@demo.test',    '$2y$12$sKod0EgmRxf8aXzVOl8kS.e36td0ApUaLnsmsI3Ywbe13cXB428zO', 'user',  NOW() - INTERVAL 5 DAY),
(6, 'fira',   'fira@demo.test',   '$2y$12$sKod0EgmRxf8aXzVOl8kS.e36td0ApUaLnsmsI3Ywbe13cXB428zO', 'user',  NOW() - INTERVAL 2 DAY);

INSERT INTO `threads` (`id`, `title`, `content`, `category_id`, `user_id`, `sticky`, `locked`, `created_at`, `bump_at`) VALUES
(1, 'Welcome ke Mamoon Forum v2!', 'Selamat datang di forum baru. Baca aturan: 1) Sopan 2) No spam 3) Semangat berdiskusi!', 1, 1, 1, 0, NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 1 HOUR),
(2, 'Rekomendasi setup coding 2026?', 'Lagi cari rekomendasi laptop + setup buat coding. Budget 15 jutaan. Ada saran?', 2, 2, 0, 0, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 30 MINUTE),
(3, 'Game apa yang lagi kamu mainin?', 'Share game yang lagi dimainin sekarang. Aku lagi ulik Elden Ring.', 3, 3, 0, 1, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 5 HOUR),
(4, 'Cara mengatasi printer tidak terdeteksi?', 'Printer di kantor mendadak tidak kedetect Windows. Kabel OK, driver sudah diinstall ulang.', 5, 4, 0, 0, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
(5, 'Tes thread tanpa kategori', 'Thread ini dibuat tanpa kategori buat ngetes tampilan.', NULL, NULL, 0, 0, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY);

INSERT INTO `posts` (`id`, `thread_id`, `user_id`, `content`, `created_at`) VALUES
(1, 1, 2, 'Terima kasih admin! Forumnya makin keren, enteng banget dibuka.', NOW() - INTERVAL 6 DAY),
(2, 1, 3, 'Iya nih, akhirnya ada forum lokal yang gak berat. Mantap!', NOW() - INTERVAL 5 DAY),
(3, 2, 1, 'Kalau budget segitu ambil yang RAM 32GB, pilih AMD ryzen. Layar minimal 15 inch.', NOW() - INTERVAL 1 DAY),
(4, 3, 4, 'Aku lagi mainin Stardew Valley lagi buat mengobati rindu.', NOW() - INTERVAL 4 DAY);

INSERT INTO `votes` (`user_id`, `target_type`, `target_id`, `vote_type`) VALUES
(1, 'thread', 1, 'up'), (2, 'thread', 1, 'up'), (3, 'thread', 1, 'up'),
(4, 'thread', 1, 'up'), (5, 'thread', 1, 'up'), (6, 'thread', 1, 'up'),
(1, 'thread', 2, 'up'), (3, 'thread', 2, 'up'),
(4, 'thread', 3, 'up'),
(1, 'post', 1, 'up'), (3, 'post', 1, 'up'), (4, 'post', 1, 'up'),
(5, 'post', 2, 'down'),
(2, 'post', 3, 'up');
