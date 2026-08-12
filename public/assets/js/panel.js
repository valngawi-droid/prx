/* ChiperX — Panel helpers umum */
'use strict';
(function () {
    // 🎬 Animasi masuk [data-anim]: inti reveal ada di CSS MURNI
    // (app.css → html.js [data-anim] { animation: cx-anim-in ... }) sehingga
    // konten PASTI tampil meski file JS ini gagal dimuat / versi lama
    // masih ke-cache. Di sini kita HANYA mengatur jeda berjenjang (stagger)
    // biar tampilannya makin manis.
    document.querySelectorAll('[data-anim]').forEach((el, i) => {
        el.style.animationDelay = Math.min(i * 45, 520) + 'ms';
    });

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
