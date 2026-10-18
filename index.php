<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LanggananKu — Tracker Langganan</title>
  
  <!-- Font Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Ikon Phosphor -->
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  
  <!-- Dexie.js (Database Lokal) -->
  <script src="https://unpkg.com/dexie@4.0.8/dist/dexie.min.js"></script>
  
  <!-- File CSS -->
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
  <!-- HEADER -->
  <header>
    <h1>Langganan<span>Ku</span></h1>
    <div class="toolbar">
      <button class="btn-secondary" id="btnExport"><i class="ph ph-download-simple"></i> Export</button>
      <button class="btn-primary" id="btnAdd"><i class="ph ph-plus"></i> Tambah</button>
      
      <div class="profile">
        <div class="avatar">
          <img src="https://ui-avatars.com/api/?name=User&background=5a4fcf&color=fff" alt="User">
          <i class="ph ph-caret-down"></i>
        </div>
      </div>
    </div>
  </header>

  <!-- SUMMARY CARDS -->
  <div class="summary">
    <div class="card">
      <div class="card-top">
        <span class="label">Total Bulanan (aktif)</span>
        <div class="card-icon primary"><i class="ph ph-wallet"></i></div>
      </div>
      <div class="value primary" id="totalMonthly">Rp 0</div>
    </div>
    
    <div class="card">
      <div class="card-top">
        <span class="label">Total Tahunan (aktif)</span>
        <div class="card-icon success"><i class="ph ph-trend-up"></i></div>
      </div>
      <div class="value success" id="totalYearly">Rp 0</div>
    </div>
    
    <div class="card">
      <div class="card-top">
        <span class="label">Langganan Aktif</span>
        <div class="card-icon info"><i class="ph ph-television"></i></div>
      </div>
      <div class="value" id="activeCount">0</div>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="filters">
    <div class="search-box">
      <i class="ph ph-magnifying-glass"></i>
      <input type="search" id="searchInput" placeholder="Cari langganan...">
    </div>
    <div class="select-box">
      <i class="ph ph-funnel"></i>
      <select id="filterStatus">
        <option value="all">Semua Status</option>
        <option value="active">Aktif</option>
        <option value="inactive">Nonaktif</option>
      </select>
    </div>
  </div>

  <!-- LIST TABLE -->
  <div class="table-container">
    <div class="table-header">
      <span>Nama Langganan</span>
      <span>Status</span>
      <span>Pengingat</span>
      <span class="text-right">Harga / Bulan</span>
      <span class="text-right">Aksi</span>
    </div>
    <div id="list"></div>
  </div>
</div>

<!-- FORM MODAL -->
<div class="modal-overlay" id="modalForm">
  <div class="modal">
    <h2 id="modalTitle">Tambah Langganan</h2>
    <form id="formSubscription">
      <input type="hidden" id="subId">
      <div class="form-group">
        <label for="subName">Nama Layanan *</label>
        <input type="text" id="subName" required placeholder="Contoh: Netflix, Spotify" maxlength="120" autocomplete="off">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="subPrice">Harga (Rp) *</label>
          <input type="number" id="subPrice" required min="0" step="1000" placeholder="50000" inputmode="numeric">
        </div>
        <div class="form-group">
          <label for="subCycle">Siklus *</label>
          <select id="subCycle" required>
            <option value="monthly">Bulanan</option>
            <option value="yearly">Tahunan</option>
            <option value="quarterly">3 Bulanan</option>
            <option value="weekly">Mingguan</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="subRenewal">Tanggal Renewal Berikutnya</label>
        <input type="date" id="subRenewal">
      </div>
      <div class="form-group">
        <label for="subNotes">Catatan (opsional)</label>
        <textarea id="subNotes" rows="2" placeholder="Contoh: Paket Premium 4K" maxlength="500"></textarea>
      </div>
      <div class="form-group">
        <label class="checkbox-label">
          <input type="checkbox" id="subActive" checked>
          <span>Langganan aktif (dihitung ke total)</span>
        </label>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="btnCancel">Batal</button>
        <button type="submit" class="btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- File JS -->
<script src="app.js"></script>
</body>
</html>