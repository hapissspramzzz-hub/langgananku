(function () {
'use strict';

if (typeof Dexie === 'undefined') return alert('Dexie.js gagal dimuat. Pastikan ada koneksi internet.');

// =========================================
// DATABASE SETUP (IndexedDB)
// =========================================
const db = new Dexie('LanggananKuDB');
db.version(1).stores({
  subscriptions: 'id, name, cycle, renewalDate, active, createdAt'
});

const state = { subs: [], filters: { search: '', status: 'all' } };
const $ = (id) => document.getElementById(id);
const uuid = () => crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); });

// =========================================
// UTILITIES
// =========================================
const fmtIDR = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);
const toMonthly = (p, c) => c === 'weekly' ? p * 52 / 12 : c === 'monthly' ? p : c === 'quarterly' ? p / 3 : c === 'yearly' ? p / 12 : 0;
const toYearly = (p, c) => c === 'weekly' ? p * 52 : c === 'monthly' ? p * 12 : c === 'quarterly' ? p * 4 : c === 'yearly' ? p : 0;
const cycleLabel = (c) => ({ weekly: 'Mingguan', monthly: 'Bulanan', quarterly: '3 Bulanan', yearly: 'Tahunan' })[c] || c;
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// Warna untuk Logo Inisial
const colors = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e'];
const getLogoColor = (str) => colors[str.length % colors.length];

function toast(msg, type = '') {
  const el = $('toast'); if(!el) return;
  el.textContent = msg; el.className = 'toast show ' + type;
  clearTimeout(el._t); el._t = setTimeout(() => el.className = 'toast ' + type, 3000);
}

// =========================================
// TIMER LOGIC
// =========================================
function getTimerStatus(renewalDate) {
  if (!renewalDate) return { text: '-', class: 'timer-normal' };
  const today = new Date(); today.setHours(0,0,0,0);
  const target = new Date(renewalDate); target.setHours(0,0,0,0);
  const diffDays = Math.ceil((target - today) / (1000 * 60 * 60 * 24));

  if (diffDays < 0) return { text: `<i class="ph ph-clock"></i> Terlewat ${Math.abs(diffDays)} hari`, class: 'timer-danger' };
  if (diffDays === 0) return { text: `<i class="ph ph-clock"></i> Hari ini!`, class: 'timer-danger' };
  if (diffDays <= 7) return { text: `<i class="ph ph-clock"></i> Sisa ${diffDays} hari`, class: 'timer-warning' };
  return { text: '', class: 'timer-normal' }; 
}

// =========================================
// CORE FUNCTIONS
// =========================================
async function loadAll() {
  state.subs = await db.subscriptions.toArray();
  render();
}

function render() {
  const active = state.subs.filter(s => s.active);
  const tm = active.reduce((sum, s) => sum + toMonthly(s.price, s.cycle), 0);
  const ty = active.reduce((sum, s) => sum + toYearly(s.price, s.cycle), 0);
  
  $('totalMonthly').textContent = fmtIDR(Math.round(tm));
  $('totalYearly').textContent = fmtIDR(Math.round(ty));
  $('activeCount').textContent = active.length;

  const list = $('list');
  if (state.subs.length === 0) {
    list.innerHTML = '<div style="text-align:center; padding:50px; color:#5f6368;"><i class="ph ph-folder-open" style="font-size:48px; margin-bottom:12px; opacity:0.5;"></i><h3>Belum ada data</h3><p>Klik "+ Tambah" untuk mulai.</p></div>';
    return;
  }

  let filtered = state.subs;
  if (state.filters.search) filtered = filtered.filter(s => s.name.toLowerCase().includes(state.filters.search.toLowerCase()));
  if (state.filters.status === 'active') filtered = filtered.filter(s => s.active);
  if (state.filters.status === 'inactive') filtered = filtered.filter(s => !s.active);

  filtered.sort((a, b) => {
    if (!a.renewalDate) return 1;
    if (!b.renewalDate) return -1;
    return new Date(a.renewalDate) - new Date(b.renewalDate);
  });

  list.innerHTML = filtered.map(s => {
    const timer = getTimerStatus(s.renewalDate);
    const initial = s.name.charAt(0);
    const bgColor = getLogoColor(s.name);
    const statusBadge = s.active 
      ? `<span class="status-badge badge-active">Aktif</span>` 
      : `<span class="status-badge badge-inactive">Nonaktif</span>`;
      
    const timerHtml = timer.text 
      ? `<span class="timer-badge ${timer.class}">${timer.text}</span>` 
      : `<span style="color:#9ca3af; font-size:13px;">—</span>`;

    return `
    <div class="list-item">
      <div class="item-main">
        <div class="item-logo" style="background-color: ${bgColor}">${esc(initial)}</div>
        <div>
          <div class="item-name">${esc(s.name)}</div>
          <div class="item-meta">${cycleLabel(s.cycle)}</div>
        </div>
      </div>
      <div>${statusBadge}</div>
      <div>${timerHtml}</div>
      <div class="item-price">${fmtIDR(s.price)}</div>
      <div>
        <button class="btn-delete" data-action="delete" data-id="${s.id}" title="Hapus"><i class="ph ph-trash"></i></button>
      </div>
    </div>
  `}).join('');
}

// =========================================
// EVENT LISTENERS
// =========================================
$('btnAdd').addEventListener('click', () => $('modalForm').classList.add('active'));
$('btnCancel').addEventListener('click', () => $('modalForm').classList.remove('active'));

$('formSubscription').addEventListener('submit', async (e) => {
  e.preventDefault();
  const name = $('subName').value.trim();
  const price = Number($('subPrice').value);
  if(!name || isNaN(price)) return alert('Nama dan Harga wajib diisi!');

  await db.subscriptions.add({
    id: uuid(),
    name: name,
    price: price,
    cycle: $('subCycle').value,
    renewalDate: $('subRenewal').value || '',
    notes: $('subNotes').value.trim(),
    active: $('subActive').checked,
    createdAt: new Date().toISOString()
  });
  
  $('modalForm').classList.remove('active');
  e.target.reset();
  toast('Data berhasil ditambahkan', 'success');
  loadAll();
});

$('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-action="delete"]');
  if (btn) {
    if(confirm('Hapus langganan ini?')) {
      await db.subscriptions.delete(btn.dataset.id);
      toast('Data dihapus', 'success');
      loadAll();
    }
  }
});

$('searchInput').addEventListener('input', (e) => { state.filters.search = e.target.value; render(); });
$('filterStatus').addEventListener('change', (e) => { state.filters.status = e.target.value; render(); });

// Export functionality
$('btnExport').addEventListener('click', async () => {
  const all = await db.subscriptions.toArray();
  const blob = new Blob([JSON.stringify(all, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = `langgananku-backup-${new Date().toISOString().slice(0,10)}.json`;
  a.click();
  URL.revokeObjectURL(url);
  toast('Berhasil export data', 'success');
});

// =========================================
// INITIALIZATION
// =========================================
loadAll();
})();