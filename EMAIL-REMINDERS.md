# Pengingat email 24 jam

Pengirim memeriksa langganan aktif yang waktu perpanjangannya berada antara 24 jam dan 24 jam 5 menit dari waktu pemeriksaan. Dengan tugas terjadwal yang berjalan setiap 5 menit, email dikirim sekitar 24 jam sebelum perpanjangan dan tidak sengaja menunggu sampai kurang dari 24 jam. Pengingat dikirim ke email akun dengan nama platform, waktu perpanjangan, dan harga per siklus. Satu langganan hanya mendapat satu email untuk satu waktu perpanjangan.

## Persiapan

1. Jalankan `email-reminder-migration.sql` sekali pada database `langgananku`.
2. Atur variabel lingkungan pada Windows untuk akun yang akan menjalankan tugas terjadwal:
   - `SMTP_HOST`: host SMTP penyedia email.
   - `SMTP_PORT`: biasanya `587` untuk TLS atau `465` untuk SSL.
   - `SMTP_USERNAME` dan `SMTP_PASSWORD`: kredensial SMTP. Gunakan app password jika penyedia memerlukannya; jangan masukkan rahasia ke file proyek atau Git.
   - `SMTP_FROM_EMAIL`: alamat pengirim yang diizinkan penyedia.
   - `SMTP_FROM_NAME`: opsional, default `LanggananKu`.
   - `SMTP_ENCRYPTION`: `tls` (default), `ssl`, atau `none`.
   - `APP_TIMEZONE`: opsional, default `Asia/Jakarta`.
3. Buka terminal baru setelah mengatur variabel lingkungan. Uji konfigurasi dan daftar email yang akan diproses tanpa mengirim email:

   ```powershell
   C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe C:\laragon\www\langganaku\send-renewal-reminders.php --dry-run
   ```

4. Buat tugas di Windows Task Scheduler untuk menjalankan perintah yang sama tanpa `--dry-run` setiap 5 menit. Atur agar berjalan dengan akun Windows yang memiliki variabel SMTP tersebut, meskipun tidak sedang login. Jika tugas tidak berjalan dalam jendela 5 menit tersebut, email tidak dapat dijamin terkirim tepat waktu.

Dashboard atau browser tidak perlu dibiarkan terbuka. Email baru dapat terkirim setelah SMTP valid dan tugas terjadwal aktif. Kegagalan pengiriman dicatat di keluaran/error log PHP dan dapat dicoba lagi pada jadwal berikutnya.
