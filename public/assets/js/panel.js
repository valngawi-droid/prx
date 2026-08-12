/* ChiperX — Panel helpers umum */
'use strict';
(function () {
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
