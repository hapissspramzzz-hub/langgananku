<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Kelola langganan, pantau pengeluaran, dan ingat jadwal perpanjangan dalam satu tempat dengan LanggananKu.">
    <title>LanggananKu - Kelola Langganan dengan Mudah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="syle.css?v=<?= filemtime(__DIR__ . '/syle.css') ?>">
</head>
<body class="landing-page">
<main class="landing-container">
    <header class="landing-header">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <nav class="landing-nav" aria-label="Navigasi utama">
            <a href="#fitur">Fitur</a>
            <a href="#cara-kerja">Cara kerja</a>
            <a class="btn btn-secondary" href="index.php?mode=login">Masuk</a>
            <a class="btn btn-primary" href="index.php?mode=register">Mulai gratis <i class="ph ph-arrow-right"></i></a>
        </nav>
    </header>
    <section class="landing-hero">
        <div class="hero-copy">
            <span class="hero-badge"><i class="ph ph-sparkle"></i> Langganan rapi, pikiran lebih tenang</span>
            <h1>Langganan banyak,<br><span>nggak perlu</span><br>pusing ngaturnya.</h1>
            <p>Harga dan tanggal tagihan yang tersebar bikin sulit dipantau. Satukan data langganan, hitung perkiraan biaya, dan cek jadwal perpanjangan dalam satu dashboard.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="index.php?mode=register">Buat akun gratis <i class="ph ph-arrow-right"></i></a>
                <a class="hero-login-link" href="index.php?mode=login">Sudah punya akun? Masuk</a>
            </div>
            <div class="hero-points"><span><i class="ph ph-check-circle"></i> Mudah digunakan</span><span><i class="ph ph-check-circle"></i> Data tersimpan pribadi</span></div>
        </div>
        <div class="hero-visual" aria-label="Contoh tampilan ringkasan LanggananKu">
            <div class="visual-glow"></div>
            <div class="preview-card">
                <div class="preview-top"><div><span class="preview-overline">DASHBOARD</span><h2>Ringkasan kamu</h2></div><span class="preview-avatar">K</span></div>
                <div class="preview-total"><span>Total pengeluaran per bulan</span><strong>Rp 428.000</strong><span class="preview-trend"><i class="ph ph-trend-up"></i> Semua langganan terpantau</span></div>
                <div class="preview-list-title"><strong>Langganan aktif</strong><span>3 layanan</span></div>
                <div class="preview-subscription"><span class="service-icon service-purple"><i class="ph ph-play"></i></span><span class="service-name"><strong>Streaming</strong><small>Perpanjangan 12 Okt</small></span><strong class="service-price">Rp 149.000</strong></div>
                <div class="preview-subscription"><span class="service-icon service-green"><i class="ph ph-music-notes"></i></span><span class="service-name"><strong>Musik</strong><small>Perpanjangan 18 Okt</small></span><strong class="service-price">Rp 55.000</strong></div>
                <div class="preview-subscription"><span class="service-icon service-orange"><i class="ph ph-cloud"></i></span><span class="service-name"><strong>Penyimpanan cloud</strong><small>Perpanjangan 24 Okt</small></span><strong class="service-price">Rp 224.000</strong></div>
            </div>
            <div class="reminder-note"><span class="reminder-icon"><i class="ph ph-bell-ringing"></i></span><span><strong>Pengingat aktif</strong><small>Perpanjangan makin dekat</small></span><i class="ph ph-check-circle reminder-check"></i></div>
        </div>
    </section>
    <section class="features-section" id="fitur">
        <div class="section-heading"><span class="eyebrow">SEMUA DALAM SATU TEMPAT</span><h2>Lebih teratur, tanpa ribet.</h2><p>Informasi penting soal langgananmu jadi gampang dilihat dan dikelola.</p></div>
        <div class="feature-grid">
            <article class="feature-card"><span class="feature-icon feature-violet"><i class="ph ph-wallet"></i></span><h3>Biaya langganan sulit dihitung?</h3><p>LanggananKu merangkum pengeluaran aktif menjadi estimasi bulanan dan tahunan, termasuk tagihan mingguan, bulanan, per 3 bulan, dan tahunan.</p></article>
            <article class="feature-card"><span class="feature-icon feature-blue"><i class="ph ph-calendar-check"></i></span><h3>Takut lupa tanggal perpanjangan?</h3><p>Lihat tanggal dan hitung mundur perpanjangan. Pengingat browser tersedia saat dashboard terbuka dan notifikasi diizinkan.</p></article>
            <article class="feature-card"><span class="feature-icon feature-green"><i class="ph ph-lock-key"></i></span><h3>Ingin data tetap tertata per akun?</h3><p>Setiap akun mengelola daftar langganannya sendiri, lengkap dengan harga, periode, jadwal, catatan, dan status aktif.</p></article>
        </div>
    </section>
    <section class="problem-section">
        <div class="section-heading"><span class="eyebrow">DARI MASALAH KE SOLUSI</span><h2>Bukan cuma mencatat. Jadi tahu apa yang perlu diperhatikan.</h2><p>Data yang rapi membantu kamu mengambil keputusan sebelum tagihan berikutnya datang.</p></div>
        <div class="problem-grid">
            <article class="problem-card">
                <span class="problem-label"><i class="ph ph-warning-circle"></i> MASALAH</span>
                <h3>Tagihan kecil terasa tidak kelihatan</h3>
                <p>Beberapa biaya berulang dari layanan berbeda mudah luput saat diperiksa satu per satu.</p>
                <div class="solution-copy"><span class="solution-label"><i class="ph ph-check-circle"></i> YANG DIBANTU LANGGANANKU</span><p>Lihat total estimasi biaya aktif per bulan dan per tahun dalam satu ringkasan.</p></div>
            </article>
            <article class="problem-card">
                <span class="problem-label"><i class="ph ph-warning-circle"></i> MASALAH</span>
                <h3>Jadwal perpanjangan sulit diingat</h3>
                <p>Tanggal layanan yang berbeda-beda berisiko terlupakan sampai tagihan berikutnya muncul.</p>
                <div class="solution-copy"><span class="solution-label"><i class="ph ph-check-circle"></i> YANG DIBANTU LANGGANANKU</span><p>Urutkan jadwal, lihat hitung mundur, dan gunakan pengingat browser saat dashboard terbuka.</p></div>
            </article>
            <article class="problem-card">
                <span class="problem-label"><i class="ph ph-warning-circle"></i> MASALAH</span>
                <h3>Catatan tersebar dan susah diperbarui</h3>
                <p>Harga, periode pembayaran, dan status layanan bisa tersimpan di banyak tempat berbeda.</p>
                <div class="solution-copy"><span class="solution-label"><i class="ph ph-check-circle"></i> YANG DIBANTU LANGGANANKU</span><p>Simpan dan perbarui rincian langganan di dashboard pribadi, dengan data terpisah per akun.</p></div>
            </article>
        </div>
        <div class="data-highlights" aria-label="Ringkasan kemampuan LanggananKu">
            <div><strong>4</strong><span>pilihan periode tagihan</span></div>
            <div><strong>2</strong><span>estimasi biaya: bulanan &amp; tahunan</span></div>
            <div><strong>1</strong><span>dashboard untuk semua catatan</span></div>
        </div>
    </section>
    <section class="steps-section" id="cara-kerja">
        <div class="section-heading"><span class="eyebrow">MULAI DALAM BEBERAPA LANGKAH</span><h2>Gampang dari awal.</h2></div>
        <div class="steps-grid">
            <article class="step-card"><span>01</span><div><h3>Buat akun</h3><p>Daftar untuk punya ruang pribadi mengelola semua langgananmu.</p></div></article>
            <article class="step-card"><span>02</span><div><h3>Catat langganan</h3><p>Tambahkan nama layanan, harga, dan tanggal perpanjangannya.</p></div></article>
            <article class="step-card"><span>03</span><div><h3>Pantau semuanya</h3><p>Cek ringkasan biaya dan jadwal perpanjangan lewat dashboard.</p></div></article>
        </div>
    </section>
    <section class="landing-cta">
        <div><span class="eyebrow">SIAP MULAI?</span><h2>Biar LanggananKu yang bantu mengingat.</h2><p>Mulai rapikan langgananmu sekarang.</p></div>
        <a class="btn btn-primary" href="index.php?mode=register">Daftar sekarang <i class="ph ph-arrow-right"></i></a>
    </section>
    <footer class="landing-footer">
        <a class="brand" href="index.php">Langganan<span>Ku</span></a>
        <span>Kelola langgananmu dengan lebih mudah.</span>
        <span class="footer-credit">by hapisss pramzzzzzzzzzzzzzzzzzzzzzzzzzzz</span>
    </footer>
</main>
</body>
</html>
