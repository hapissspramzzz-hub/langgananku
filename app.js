document.addEventListener('DOMContentLoaded', () => {
    // Cek apakah kita sedang di halaman dashboard (ada elemen #list)
    if (!document.getElementById('list')) return;

    const list = document.getElementById('list');
    const totalMonthlyEl = document.getElementById('totalMonthly');
    const totalYearlyEl = document.getElementById('totalYearly');
    const activeCountEl = document.getElementById('activeCount');
    const modalForm = document.getElementById('modalForm');
    const form = document.getElementById('formSubscription');

    const fmtIDR = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);
    const toMonthly = (p, c) => c === 'monthly' ? p : c === 'yearly' ? p / 12 : p;

    async function loadData() {
        const res = await fetch('api.php?action=list');
        const data = await res.json();
        render(data);
    }

    function render(subscriptions) {
        let tm = 0;
        let active = 0;

        if (subscriptions.length === 0) {
            list.innerHTML = '<div style="padding:30px; text-align:center; color:#666;">Belum ada data. Klik "Tambah".</div>';
            totalMonthlyEl.textContent = fmtIDR(0);
            totalYearlyEl.textContent = fmtIDR(0);
            activeCountEl.textContent = 0;
            return;
        }

        list.innerHTML = subscriptions.map(s => {
            if (s.active == 1) {
                tm += toMonthly(parseFloat(s.price), s.cycle);
                active++;
            }

            // Logika Timer
            let timerHtml = '<span style="color:#9ca3af; font-size:13px;">-</span>';
            if (s.renewal_date) {
                const today = new Date(); today.setHours(0,0,0,0);
                const target = new Date(s.renewal_date); target.setHours(0,0,0,0);
                const diff = Math.ceil((target - today) / (1000 * 60 * 60 * 24));

                if (diff < 0) timerHtml = `<span class="timer-badge timer-danger"><i class="ph ph-clock"></i> Terlewat ${Math.abs(diff)} hari</span>`;
                else if (diff === 0) timerHtml = `<span class="timer-badge timer-danger"><i class="ph ph-clock"></i> Hari ini!</span>`;
                else if (diff <= 7) timerHtml = `<span class="timer-badge timer-warning"><i class="ph ph-clock"></i> Sisa ${diff} hari</span>`;
                else timerHtml = `<span class="timer-badge timer-normal"><i class="ph ph-clock"></i> ${diff} hari lagi</span>`;
            }

            const colors = ['#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6'];
            const color = colors[s.name.length % 5];

            return `
            <div class="list-item">
                <div class="item-main">
                    <div class="item-logo" style="background-color: ${color}">${s.name.charAt(0)}</div>
                    <div>
                        <div class="item-name">${s.name}</div>
                        <div class="item-meta">${s.cycle === 'monthly' ? 'Bulanan' : 'Tahunan'}</div>
                    </div>
                </div>
                <div><span class="status-badge">Aktif</span></div>
                <div>${timerHtml}</div>
                <div class="item-price">${fmtIDR(s.price)}</div>
                <div>
                    <button class="btn-delete" onclick="deleteSub('${s.id}')"><i class="ph ph-trash"></i></button>
                </div>
            </div>`;
        }).join('');

        totalMonthlyEl.textContent = fmtIDR(tm);
        totalYearlyEl.textContent = fmtIDR(tm * 12);
        activeCountEl.textContent = active;
    }

    // Event Submit Form Tambah
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            name: document.getElementById('subName').value,
            price: document.getElementById('subPrice').value,
            cycle: document.getElementById('subCycle').value,
            renewalDate: document.getElementById('subRenewal').value
        };

        await fetch('api.php?action=add', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });

        modalForm.classList.remove('active');
        form.reset();
        loadData(); // Refresh tabel
    });

    // Fungsi Hapus (dipanggil dari onclick di HTML)
    window.deleteSub = async (id) => {
        if (!confirm('Hapus langganan ini?')) return;
        await fetch('api.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id})
        });
        loadData(); // Refresh tabel
    };

    // Panggil data pertama kali
    loadData();
});