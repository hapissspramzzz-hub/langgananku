-- Nama layanan dapat disimpan tanpa harga sampai pengguna melengkapinya.
ALTER TABLE user_subscriptions
    MODIFY COLUMN price DECIMAL(12, 2) NULL;
