/* ============================================================
   ChiperX — Mini Games (client): roda gacha, mystery box, card flip
   Hasil TETAP ditentukan server (RNG PHP) — JS hanya menganimasikannya.
   ============================================================ */
'use strict';

(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const ticketsEl = document.getElementById('ticketCount');
    const coinsEl = document.getElementById('coinCount');
    let playing = false;

    // ---------- Util ----------
    function updateCounters(data) {
        if (data.tickets_left !== undefined) ticketsEl.textContent = data.tickets_left;
        if (data.coin_balance !== undefined) coinsEl.textContent = Number(data.coin_balance).toLocaleString('id-ID');
    }

    function lockIfNoTickets() {
        if (ticketsEl && parseInt(ticketsEl.textContent || '0', 10) <= 0) {
            const spin = document.getElementById('spinBtn');
            if (spin) { spin.disabled = true; spin.textContent = 'HABIS'; }
        }
    }

    async function playOnServer(game) {
        const res = await fetch(window.GAME_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ game }),
        });
        return res.json();
    }

    function paintResult(el, data) {
        if (!el) return;
        const win = data.result && data.result.reward > 0;
        el.textContent = data.message;
        el.className = 'mt-2 font-display text-xl font-bold min-h-[1.75rem] ' + (win ? 'text-emerald-300 drop-shadow-[0_0_15px_rgba(74,222,128,.7)]' : 'text-red-400/90');
    }

    // Toast perayaan saat achievement terbuka
    function showAchievements(list) {
        (list || []).forEach((a, i) => setTimeout(() => {
            const el = document.createElement('div');
            el.className = 'fixed left-1/2 z-50 px-6 py-4 rounded-2xl border border-amber-400/50 bg-slate-900/95 backdrop-blur-xl shadow-2xl text-center';
            el.style.bottom = '28px';
            el.style.transform = 'translateX(-50%) translateY(20px)';
            el.style.opacity = '0';
            el.style.transition = 'all .35s ease';
            el.innerHTML = `<div class="text-3xl">${a.icon}</div>
                <b class="text-amber-300 text-sm">Achievement Terbuka: ${a.name}</b>
                ${a.reward ? `<div class="text-xs text-slate-400 mt-0.5">Bonus +${a.reward} Coin 🪙</div>` : ''}`;
            document.body.appendChild(el);
            requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'translateX(-50%) translateY(0)'; });
            setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(-50%) translateY(10px)'; setTimeout(() => el.remove(), 400); }, 3400);
        }, i * 1600));
    }

    // ---------- TAB SWITCH ----------
    document.querySelectorAll('.game-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.game-tab').forEach((b) => {
                b.className = 'game-tab px-5 py-2.5 rounded-xl text-sm font-semibold border transition bg-white/5 border-white/10 text-slate-400 hover:text-white';
            });
            btn.className = 'game-tab px-5 py-2.5 rounded-xl text-sm font-semibold border transition bg-violet-600/30 border-violet-500/50 text-white';
            document.querySelectorAll('.game-panel').forEach((p) => p.classList.add('hidden'));
            document.getElementById('tab-' + btn.dataset.tab)?.classList.remove('hidden');
        });
    });

    // ============================================================
    // GAME A: GACHA SPIN — roda canvas dengan easing fisika
    // ============================================================
    const wheel = document.getElementById('gachaWheel');
    const spinBtn = document.getElementById('spinBtn');
    if (wheel && spinBtn) {
        const ctx = wheel.getContext('2d');
        const SEG = window.GACHA_SEGMENTS ?? [];
        const N = SEG.length || 1;
        const R = 320; // view 640x640
        let angle = 0;

        function drawWheel() {
            ctx.clearRect(0, 0, 640, 640);
            const arc = (Math.PI * 2) / N;
            for (let i = 0; i < N; i++) {
                const a0 = angle + i * arc;
                const a1 = a0 + arc;
                ctx.beginPath();
                ctx.moveTo(R, R);
                ctx.arc(R, R, R - 8, a0, a1);
                ctx.closePath();
                ctx.fillStyle = SEG[i].color + (SEG[i].key === 'zonk' ? '55' : 'cc');
                ctx.fill();
                ctx.strokeStyle = 'rgba(15,23,42,.9)';
                ctx.lineWidth = 4;
                ctx.stroke();
                // Label
                ctx.save();
                ctx.translate(R, R);
                ctx.rotate(a0 + arc / 2);
                ctx.textAlign = 'right';
                ctx.fillStyle = '#fff';
                ctx.font = `bold ${SEG[i].key === 'zonk' ? 34 : 44}px Space Grotesk, sans-serif`;
                ctx.shadowColor = SEG[i].color;
                ctx.shadowBlur = 18;
                ctx.fillText(SEG[i].label, R - 40, 12);
                ctx.restore();
            }
        }
        drawWheel();

        spinBtn.addEventListener('click', async () => {
            if (playing) return;
            playing = true;
            spinBtn.disabled = true;
            spinBtn.textContent = '...';
            const resultEl = document.getElementById('gachaResult');
            resultEl.textContent = '';

            const data = await playOnServer('gacha');
            if (!data.ok) {
                paintResult(resultEl, { message: data.message });
                playing = false;
                spinBtn.disabled = false;
                spinBtn.textContent = 'SPIN';
                updateCounters(data);
                lockIfNoTickets();
                return;
            }

            // Cari segmen hadiah: ambil segmen reward yang cocok, jika tidak ada (zonk) pilih segmen zonk acak
            const key = data.result.key;
            let candidates = SEG.map((s, i) => (s.key === key ? i : -1)).filter((i) => i >= 0);
            if (candidates.length === 0) candidates = SEG.map((s, i) => (s.key === 'zonk' ? i : -1)).filter((i) => i >= 0);
            const segIndex = candidates[Math.floor(Math.random() * candidates.length)];

            // Target: pointer (atas, -90°) tepat di TENGAH segmen terpilih
            const arc = (Math.PI * 2) / N;
            const targetAngle = (-Math.PI / 2) - (segIndex * arc + arc / 2);
            const spins = 5 + Math.random() * 2;
            const current = angle % (Math.PI * 2);
            let delta = targetAngle - current;
            while (delta < 0) delta += Math.PI * 2;
            const destination = angle + spins * Math.PI * 2 + delta;

            const start = performance.now();
            const duration = 4200;
            const from = angle;
            (function spin(now) {
                const p = Math.min((now - start) / duration, 1);
                const ease = 1 - Math.pow(1 - p, 4); // easeOutQuart — berhenti mulus
                angle = from + (destination - from) * ease;
                drawWheel();
                if (p < 1) {
                    requestAnimationFrame(spin);
                } else {
                    paintResult(resultEl, data);
                    updateCounters(data);
                    showAchievements(data.achievements);
                    playing = false;
                    spinBtn.textContent = 'SPIN';
                    spinBtn.disabled = false;
                    lockIfNoTickets();
                }
            })(start);
        });
        lockIfNoTickets();
    }

    // ============================================================
    // GAME B: MYSTERY BOX
    // ============================================================
    document.querySelectorAll('.mystery-box').forEach((box) => {
        box.addEventListener('click', async () => {
            if (playing) return;
            playing = true;
            document.querySelectorAll('.mystery-box').forEach((b) => b.style.pointerEvents = 'none');

            const data = await playOnServer('mystery_box');
            // Animasi buka tutup kotak terpilih
            const lid = box.querySelector('.box-lid');
            const prize = box.querySelector('.box-prize');
            lid.style.transform = 'rotateX(120deg) translateY(-14px)';
            setTimeout(() => {
                if (data.ok) {
                    prize.textContent = data.result.reward > 0 ? '🪙' : '💨';
                    prize.style.opacity = '1';
                    prize.style.transform = 'scale(1.25)';
                } else {
                    prize.textContent = '⚠️';
                    prize.style.opacity = '1';
                }
                paintResult(document.getElementById('boxResult'), data.ok ? data : { message: data.message });
                updateCounters(data);
                if (data.ok) showAchievements(data.achievements);
            }, 450);

            setTimeout(() => {
                lid.style.transform = '';
                prize.style.opacity = '0';
                prize.style.transform = '';
                document.querySelectorAll('.mystery-box').forEach((b) => b.style.pointerEvents = '');
                playing = false;
                lockIfNoTickets();
            }, 2600);
        });
    });

    // ============================================================
    // GAME C: CARD FLIP
    // ============================================================
    document.querySelectorAll('.flip-card').forEach((card) => {
        card.addEventListener('click', async () => {
            if (playing) return;
            playing = true;
            document.querySelectorAll('.flip-card').forEach((c) => c.style.pointerEvents = 'none');

            const data = await playOnServer('card_flip');
            const inner = card.querySelector('.flip-inner');
            const back = card.querySelector('.flip-back');

            setTimeout(() => {
                if (data.ok) {
                    back.textContent = data.result.reward > 0 ? `+${data.result.reward} 🪙` : 'ZONK 💨';
                    back.classList.remove('text-white');
                    back.classList.add(data.result.reward > 0 ? 'text-emerald-300' : 'text-red-400');
                } else {
                    back.textContent = '⚠️';
                }
                inner.style.transform = 'rotateY(180deg)';
                paintResult(document.getElementById('cardResult'), data.ok ? data : { message: data.message });
                updateCounters(data);
                if (data.ok) showAchievements(data.achievements);
            }, 250);

            setTimeout(() => {
                inner.style.transform = '';
                document.querySelectorAll('.flip-card').forEach((c) => c.style.pointerEvents = '');
                playing = false;
                lockIfNoTickets();
            }, 2600);
        });
    });
})();
