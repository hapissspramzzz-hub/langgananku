document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    if (!toggle || !password) return;

    toggle.addEventListener('click', () => {
        const isVisible = password.type === 'text';
        password.type = isVisible ? 'password' : 'text';
        toggle.textContent = isVisible ? 'Lihat' : 'Sembunyikan';
        toggle.setAttribute('aria-pressed', String(!isVisible));
    });
});
