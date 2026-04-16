-- Fix passwords for test accounts
-- ADMIN: 20-1234-567 / admin123
-- STUDENT: 20-1111-111 / student123

UPDATE `users` SET `password` = 'admin123' WHERE `id_number` = '20-1234-567';
UPDATE `users` SET `password` = 'student123' WHERE `id_number` = '20-1111-111';
UPDATE `users` SET `password` = 'student123' WHERE `id_number` = '20-2222-222';
UPDATE `users` SET `password` = 'student123' WHERE `id_number` = '20-3333-333';
