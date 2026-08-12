/* ChiperX — Panel helpers umum */
'use strict';
(function () {
    // 🎬 WAJIB: tampilkan semua [data-anim] — panel TIDAK memuat GSAP.
    // CSS menyembunyikan [data-anim] saat html.js; tanpa reveal ini konten
    // panel (dashboard/owner/admin/dll.) tampak "kosong" selamanya.
    document.querySelectorAll('[data-anim]').forEach((el, i) => {
        el.style.transition = 'opacity .45s ease, transform .45s ease';
        el.style.transform = 'translateY(10px)';
        setTimeout(() => { el.style.opacity = '1'; el.style.transform = 'none'; }, 25 + i * 35);
    });
    // Failsafe absolut: apa pun yang terjadi, setelah 1.5 dtk semuanya tampil
    setTimeout(() => {
        document.querySelectorAll('[data-anim]').forEach((el) => { el.style.opacity = '1'; el.style.transform = 'none'; });
    }, 1500);

    // Tutup dialog saat klik backdrop
    document.querySelectorAll('dialog.modal-glass').forEach((dlg) => {
        dlg.addEventListener('click', (e) => {
            if (e.target === dlg) dlg.close();
        });
    });
    // Konfirmasi global untuk form berbahaya
    document.querySelectorAll('form[data-confirm]').forEach((f) => {
        f.addEventListener('submit', (e) => {
            if (!confirm(f.dataset.confirm)) e.preventDefault();
        });
    });
})();
