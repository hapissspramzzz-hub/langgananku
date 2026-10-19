-- Jalankan sekali setelah memilih database langgananku.
ALTER TABLE users
    ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user';

-- Ganti alamat email di bawah dengan email akun yang sudah terdaftar,
-- lalu jalankan perintah ini untuk menjadikan akun tersebut admin.
UPDATE users
SET role = 'admin'
WHERE email = 'admin@example.com';
