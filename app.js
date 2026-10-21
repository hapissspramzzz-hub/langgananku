document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('list');
    if (!list) return;
    const toastRoot = document.getElementById('toastRoot');
    const cycleNames = { weekly: 'Mingguan', monthly: 'Bulanan', quarterly: 'Per 3 bulan', yearly: 'Tahunan' };
    const thresholds = [
        { seconds: 604800, label: '7 hari' }, { seconds: 432000, label: '5 hari' },
        { seconds: 172800, label: '2 hari' }, { seconds: 86400, label: '1 hari' },
        { seconds: 3600, label: '1 jam' }, { seconds: 1800, label: '30 menit' },
        { seconds: 600, label: '10 menit' }
    ];
    const deliveredReminders = new Set();
    const failedNotifications = new Set();
    const notificationButton = document.getElementById('enableNotifications');
    const money = value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const monthly = (price, cycle) => ({ weekly: price * 52 / 12, monthly: price, quarterly: price / 3, yearly: price / 12 }[cycle] ?? price);
    function toast(message, type = 'info') {
        const node = document.createElement('div'); node.className = `toast toast-${type}`; node.textContent = message;
        toastRoot.appendChild(node); window.setTimeout(() => node.remove(), 6000);
    }
    function expiryOf(item) {
        if (!item.renewal_date) return null;
        const date = String(item.renewal_date).slice(0, 10);
        const time = item.renewal_time ? String(item.renewal_time).slice(0, 8) : '23:59:59';
        const value = new Date(`${date}T${time.length === 5 ? `${time}:00` : time}`);
        return Number.isNaN(value.getTime()) ? null : value;
    }
    function remainingText(expiry) {
        const seconds = Math.floor((expiry.getTime() - Date.now()) / 1000);
        if (seconds < 0) {
            const late = Math.abs(seconds);
            if (late < 3600) return `Terlewat ${Math.max(1, Math.floor(late / 60))} menit`;
            if (late < 86400) return `Terlewat ${Math.floor(late / 3600)} jam`;
            return `Terlewat ${Math.floor(late / 86400)} hari`;
        }
        const days = Math.floor(seconds / 86400), hours = Math.floor(seconds % 86400 / 3600), minutes = Math.floor(seconds % 3600 / 60);
        if (days) return `${days} hari ${hours} jam lagi`;
        if (hours) return `${hours} jam ${minutes} menit lagi`;
        return `${minutes} menit lagi`;
    }
    function remind(item, label, expiry) {
        const key = `langgananku:${item.id}:${item.renewal_date}:${item.renewal_time || '23:59'}:${label}`;
        const message = label === '1 hari'
            ? `Langganan ${item.name} Anda sudah tersisa 24 jam lagi, segera bayar sebelum basi!!! Harga: ${money(item.price)} per ${(cycleNames[item.cycle] || item.cycle).toLowerCase()}.`
            : `${item.name} akan berakhir dalam ${label} (${expiry.toLocaleString('id-ID')}).`;
        if (!wasDelivered(`${key}:toast`)) {
            toast(message, 'warning');
            markDelivered(`${key}:toast`);
        }
        if (!('Notification' in window) || Notification.permission !== 'granted'
            || failedNotifications.has(key) || wasDelivered(`${key}:notification`)) return;

        try {
            new Notification('Pengingat LanggananKu', { body: message, tag: key });
            markDelivered(`${key}:notification`);
        } catch (error) {
            if (!failedNotifications.has(key)) {
                failedNotifications.add(key);
                console.error('Notifikasi browser gagal ditampilkan.', error);
                toast('Pengingat tampil di halaman, tetapi notifikasi browser gagal ditampilkan.', 'error');
            }
        }
    }
    function wasDelivered(key) {
        if (deliveredReminders.has(key)) return true;
        try {
            if (localStorage.getItem(key) === '1') {
                deliveredReminders.add(key);
                return true;
            }
        } catch (error) {
            console.warn('Penyimpanan pengingat tidak tersedia; pengingat hanya berlaku selama halaman terbuka.', error);
        }
        return false;
    }
    function markDelivered(key) {
        deliveredReminders.add(key);
        try {
            localStorage.setItem(key, '1');
        } catch (error) {
            console.warn('Pengingat tidak dapat disimpan antar sesi; pengingat hanya berlaku selama halaman terbuka.', error);
        }
    }
    function updateNotificationButton() {
        if (!notificationButton) return;
        if (!('Notification' in window)) {
            notificationButton.innerHTML = '<i class="ph ph-bell-slash"></i> Notifikasi tidak tersedia';
            notificationButton.title = 'Browser ini tidak mendukung notifikasi sistem.';
        } else if (Notification.permission === 'granted') {
            notificationButton.innerHTML = '<i class="ph ph-bell-ringing"></i> Notifikasi aktif';
            notificationButton.title = 'Notifikasi browser aktif selama dashboard terbuka.';
        } else if (Notification.permission === 'denied') {
            notificationButton.innerHTML = '<i class="ph ph-bell-slash"></i> Notifikasi diblokir';
            notificationButton.title = 'Izinkan notifikasi untuk situs ini melalui pengaturan browser.';
        } else {
            notificationButton.innerHTML = '<i class="ph ph-bell"></i> Aktifkan notifikasi';
            notificationButton.title = 'Izinkan notifikasi browser untuk pengingat langganan.';
        }
    }
    function checkReminders(items) {
        items.filter(item => Number(item.active) === 1).forEach(item => {
            const expiry = expiryOf(item); if (!expiry) return;
            const left = Math.floor((expiry.getTime() - Date.now()) / 1000);
            if (left <= 0) return;
            for (let i = 0; i < thresholds.length; i++) {
                const threshold = thresholds[i];
                const nextThreshold = thresholds[i + 1];
                const inWindow = threshold.label === '1 hari'
                    ? left >= threshold.seconds && left <= threshold.seconds + 30
                    : left <= threshold.seconds
                        && (!nextThreshold || left > nextThreshold.seconds + (nextThreshold.label === '1 hari' ? 30 : 0));
                if (inWindow) {
                    remind(item, threshold.label, expiry); break;
                }
            }
        });
    }
    function updateCountdowns() {
        list.querySelectorAll('[data-expiry]').forEach(node => {
            const stamp = Number(node.dataset.expiry); if (stamp) node.textContent = remainingText(new Date(stamp));
        });
    }
    function render(items) {
        let total = 0, activeCount = 0;
        items.forEach(item => { if (Number(item.active) === 1) { total += monthly(Number(item.price), item.cycle); activeCount++; } });
        document.getElementById('totalMonthly').textContent = money(total);
        document.getElementById('totalYearly').textContent = money(total * 12);
        document.getElementById('activeCount').textContent = activeCount;
        if (!items.length) {
            list.innerHTML = '<div class="empty-state">Belum ada langganan. Tekan <strong>Tambah langganan</strong> untuk mulai mencatat.</div>';
            return;
        }
        list.innerHTML = items.map(item => {
            const expiry = expiryOf(item), active = Number(item.active) === 1;
            const when = expiry ? `${expiry.toLocaleDateString('id-ID')} pukul ${expiry.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}` : 'Tanggal belum diatur';
            const left = expiry ? expiry.getTime() - Date.now() : Infinity;
            const tone = !expiry ? 'timer-normal' : left <= 86400000 ? 'timer-danger' : left <= 604800000 ? 'timer-warning' : 'timer-normal';
            return `<article class="list-item ${active ? '' : 'is-inactive'}">
              <div class="item-main"><div class="item-logo">${esc(item.name.slice(0, 1))}</div><div><div class="item-name">${esc(item.name)}</div><div class="item-meta">${esc(cycleNames[item.cycle] || item.cycle)}${item.notes ? `  -  ${esc(item.notes)}` : ''}</div></div></div>
              <div><span class="status-badge ${active ? '' : 'status-paused'}">${active ? 'Aktif' : 'Dijeda'}</span></div>
              <div><span class="timer-badge ${tone}" ${expiry ? `data-expiry="${expiry.getTime()}"` : ''}>${expiry ? esc(remainingText(expiry)) : 'Atur tanggal renewal'}</span><div class="item-meta">${esc(when)}</div></div>
              <div class="item-price">${money(item.price)}<div class="item-meta">per ${esc((cycleNames[item.cycle] || item.cycle).toLowerCase())}</div></div>
              <div class="item-actions"><a class="btn-icon" href="edit.php?id=${encodeURIComponent(item.id)}" title="Edit langganan" aria-label="Edit langganan"><i class="ph ph-pencil-simple"></i></a><a class="btn-icon btn-delete" href="hapus.php?id=${encodeURIComponent(item.id)}" title="Hapus langganan" aria-label="Hapus langganan"><i class="ph ph-trash"></i><span class="item-action-label">Hapus</span></a></div>
            </article>`;
        }).join('');
    }
    async function api(action) {
        const response = await fetch(`api.php?action=${encodeURIComponent(action)}`);
        if (!response.headers.get('content-type')?.includes('application/json')) {
            throw new Error(`Server mengirim respons non-JSON (HTTP ${response.status}). Periksa endpoint api.php.`);
        }
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'Permintaan gagal.');
        return result;
    }
    let subscriptions = [];
    async function loadData() {
        try { const result = await api('list'); subscriptions = result.items || []; render(subscriptions); checkReminders(subscriptions); }
        catch (error) { toast(error.message, 'error'); }
    }
    notificationButton.addEventListener('click', async () => {
        if (!('Notification' in window)) {
            toast('Browser ini tidak mendukung notifikasi sistem. Pengingat tetap tampil di halaman.', 'error');
            updateNotificationButton();
            return;
        }
        if (!window.isSecureContext) {
            toast('Notifikasi perlu dibuka melalui localhost atau HTTPS.', 'error');
            return;
        }
        try {
            const permission = Notification.permission === 'default'
                ? await Notification.requestPermission()
                : Notification.permission;
            updateNotificationButton();
            if (permission !== 'granted') {
                toast('Notifikasi diblokir. Izinkan notifikasi untuk situs ini melalui pengaturan browser.', 'error');
                return;
            }

            new Notification('LanggananKu', {
                body: 'Notifikasi aktif. Pengingat akan muncul saat waktu perpanjangan mendekat.',
                tag: 'langgananku:notification-test'
            });
            toast('Notifikasi browser aktif.', 'success');
            checkReminders(subscriptions);
        } catch (error) {
            updateNotificationButton();
            console.error('Permintaan izin atau notifikasi browser gagal.', error);
            toast('Notifikasi gagal diaktifkan. Periksa izin notifikasi di pengaturan browser.', 'error');
        }
    });
    updateNotificationButton();
    loadData();
    window.setInterval(() => { updateCountdowns(); checkReminders(subscriptions); }, 15000);
    window.setInterval(loadData, 60000);
});
