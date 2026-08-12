/* ============================================================
   ChiperX — Landing: animasi masuk elemen, counter statistik, sapaan
   ============================================================ */
'use strict';

(function () {
    // ---- Sapaan dinamis berdasarkan jam WIB ----
    const greeting = document.getElementById('heroGreeting');
    if (greeting) {
        const hour = parseInt(new Date().toLocaleString('en-US', { hour: 'numeric', hour12: false, timeZone: 'Asia/Jakarta' }), 10);
        const sapa = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 18 ? 'Selamat sore' : 'Selamat malam';
        greeting.insertAdjacentHTML('beforebegin',
            `<p class="text-neon-purple font-semibold tracking-widest text-sm mb-1" data-anim="fade-up">${sapa}, Pejuang Digital ⚡</p>`);
    }

    // ---- Animasi masuk viewport ----
    // FONDASI: app.css punya `html.js [data-anim] { animation: cx-anim-in … }`
    // → konten SELALU tampil walau GSAP/file ini gagal dimuat (failsafe total).
    // Bila GSAP tersedia, kita ambil alih efeknya (menonaktifkan animasi CSS
    // pada elemen tsb) supaya reveal-on-scroll lebih hidup.
    if (window.gsap) {
        const reveal = (el, delay) => {
            el.style.animation = 'none'; // serahkan ke GSAP
            gsap.fromTo(el,
                { opacity: 0, y: el.dataset.anim === 'zoom' ? 0 : 32, scale: el.dataset.anim === 'zoom' ? 0.92 : 1 },
                { opacity: 1, y: 0, scale: 1, duration: 0.75, delay: Math.min(delay, 400) / 1000, ease: 'power3.out' });
        };
        const siblingDelay = (el) =>
            Array.prototype.indexOf.call(el.parentElement ? el.parentElement.children : [], el) * 60;

        const io = ('IntersectionObserver' in window) ? new IntersectionObserver((entries) => {
            entries.forEach((en) => {
                if (!en.isIntersecting) return;
                reveal(en.target, siblingDelay(en.target));
                io.unobserve(en.target);
            });
        }, { threshold: 0.12 }) : null;

        document.querySelectorAll('[data-anim]').forEach((el) => {
            const nearTop = el.getBoundingClientRect().top < (window.innerHeight || 800) * 0.92;
            if (nearTop || !io) {
                reveal(el, siblingDelay(el));
            } else {
                el.style.animation = 'none';
                el.style.opacity = '0';
                io.observe(el);
            }
        });
    }
    // Tanpa GSAP → animasi CSS murni bekerja otomatis; tidak ada yang disembunyikan.

    // ---- Counter statistik ----
    const counters = document.querySelectorAll('.counter');
    const counterIO = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            const target = parseInt(el.dataset.target || '0', 10);
            const dur = 1600;
            const start = performance.now();
            (function tick(now) {
                const p = Math.min((now - start) / dur, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.floor(target * eased).toLocaleString('id-ID');
                if (p < 1) requestAnimationFrame(tick);
            })(start);
            counterIO.unobserve(el);
        });
    }, { threshold: 0.5 });
    counters.forEach((c) => counterIO.observe(c));
})();
