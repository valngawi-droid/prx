<?php
/** ALL LINK CHIPERX — Bento Grid ala Linktree, data dinamis dari DB. */
$icons = [
    'discord'   => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.23.46a18.3 18.3 0 0 1 4.53 2.32A18.6 18.6 0 0 0 12 4.05c-2.66 0-5.22.6-7.7 1.73A18.3 18.3 0 0 1 8.83 3.46L8.6 3a19.8 19.8 0 0 0-4.9 1.4A20.4 20.4 0 0 0 .18 18.06a19.9 19.9 0 0 0 6.06 3.06l.49-.67a12.9 12.9 0 0 1-2.04-.98l.5-.37a14.2 14.2 0 0 0 12.06 0l.5.37c-.65.39-1.34.72-2.05.98l.5.67a19.9 19.9 0 0 0 6.05-3.06A20.4 20.4 0 0 0 20.3 4.4ZM8.02 15.33c-1.18 0-2.16-1.08-2.16-2.42s.95-2.42 2.16-2.42 2.18 1.09 2.16 2.42c0 1.34-.95 2.42-2.16 2.42Zm7.97 0c-1.18 0-2.16-1.08-2.16-2.42s.95-2.42 2.16-2.42 2.18 1.09 2.16 2.42c0 1.34-.95 2.42-2.16 2.42Z"/></svg>',
    'telegram'  => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M21.94 4.64 2.72 11.6c-1.31.5-1.3 1.22-.24 1.54l4.94 1.54 1.9 5.8c.23.64.4.87.83.87.54 0 .78-.25 1.22-.55l2.93-2.84 5.1 3.76c.94.52 1.6.25 1.85-.87l3.35-15.78c.34-1.36-.49-1.98-1.41-1.63ZM7.5 14.14l10.9-6.87c.49-.3.94-.14.57.22l-9.3 8.4-.37 3.77-1.8-5.52Z"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M12 2.2c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.7 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.22-1.66 4.77-4.92 4.92-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92C2.21 15.58 2.2 15.2 2.2 12s.01-3.58.07-4.85C2.42 3.93 3.93 2.42 7.15 2.27 8.42 2.21 8.8 2.2 12 2.2Zm0 3.7a6.1 6.1 0 1 0 0 12.2 6.1 6.1 0 0 0 0-12.2Zm0 10.06a3.96 3.96 0 1 1 0-7.92 3.96 3.96 0 0 1 0 7.92Zm6.35-11.77a1.43 1.43 0 1 0 0 2.85 1.43 1.43 0 0 0 0-2.85Z"/></svg>',
    'github'    => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M12 .3a12 12 0 0 0-3.79 23.4c.6.1.82-.26.82-.58v-2.03c-3.34.72-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.09-.75.08-.73.08-.73 1.2.08 1.84 1.24 1.84 1.24 1.07 1.84 2.81 1.3 3.5 1 .1-.78.42-1.31.76-1.61-2.66-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.13-.3-.54-1.52.11-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.3-1.55 3.3-1.23 3.3-1.23.66 1.66.25 2.88.12 3.18.77.84 1.23 1.91 1.23 3.22 0 4.61-2.8 5.62-5.48 5.92.43.37.81 1.1.81 2.22v3.29c0 .32.22.7.83.58A12 12 0 0 0 12 .3Z"/></svg>',
    'youtube'   => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M23.5 6.2a3 3 0 0 0-2.12-2.13C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.52A3 3 0 0 0 .5 6.2 31.3 31.3 0 0 0 0 12a31.3 31.3 0 0 0 .5 5.8 3 3 0 0 0 2.12 2.13c1.88.52 9.38.52 9.38.52s7.5 0 9.38-.52a3 3 0 0 0 2.12-2.13A31.3 31.3 0 0 0 24 12a31.3 31.3 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.27 3.6-6.27 3.6Z"/></svg>',
    'web'       => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm7.93 9h-3.02a15.7 15.7 0 0 0-1.2-4.99A8.02 8.02 0 0 1 19.93 11ZM12 4.07c.78 1.1 1.7 2.92 1.98 4.93h-3.96c.28-2.01 1.2-3.83 1.98-4.93ZM8.29 6.01A15.7 15.7 0 0 0 7.09 11H4.07a8.02 8.02 0 0 1 4.22-4.99ZM4.07 13h3.02c.16 2.07.6 3.72 1.2 4.99A8.02 8.02 0 0 1 4.07 13ZM12 19.93c-.78-1.1-1.7-2.92-1.98-4.93h3.96c-.28 2.01-1.2 3.83-1.98 4.93Zm3.71-1.94c.6-1.27 1.04-2.92 1.2-4.99h3.02a8.02 8.02 0 0 1-4.22 4.99Z"/></svg>',
    'link'      => '<svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor"><path d="M13.06 8.11 15.9 5.26a3 3 0 0 1 4.24 4.24l-3.53 3.54a3 3 0 0 1-4.24 0l1.41-1.41a1 1 0 0 0 1.42 0l2.83-2.83a1 1 0 1 0-1.42-1.42l-2.83 2.83-1.41-1.41ZM9.5 15.9l-2.83 2.83a1 1 0 1 1-1.42-1.42l2.83-2.83a1 1 0 0 0 0-1.41l-1.41-1.42a3 3 0 0 0 0 4.25l3.53 3.53a3 3 0 0 0 4.24-4.24l-2.83-2.83a3 3 0 0 0-4.24 0l1.42 1.41a1 1 0 0 1 0 1.42Z"/></svg>',
];
?>
<section class="max-w-4xl mx-auto px-6 py-20">
    <header class="text-center" data-anim="fade-up">
        <div class="w-24 h-24 mx-auto rounded-3xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display text-4xl font-bold text-white shadow-[0_0_50px_rgba(139,92,246,.6)]">CX</div>
        <h1 class="mt-6 font-display text-3xl sm:text-4xl font-bold text-white">@CHIPERX</h1>
        <p class="mt-2 text-slate-400 text-sm">Satu halaman untuk semua kehadiran kami 🌐</p>
    </header>

    <!-- BENTO GRID -->
    <div class="grid sm:grid-cols-2 gap-4 mt-12">
        <?php if (empty($links)): ?>
            <p class="sm:col-span-2 text-center text-slate-500 glass-card p-8">Belum ada link aktif — Owner dapat menambahkannya via panel.</p>
        <?php endif; ?>
        <?php foreach ($links as $i => $link): ?>
            <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"
               class="link-card group relative glass-card p-6 flex items-center gap-5 overflow-hidden
                      hover:scale-[1.03] hover:-translate-y-1 transition duration-300 <?= $i === 0 ? 'sm:col-span-2' : '' ?>"
               style="--glow: <?= e($link['color']) ?>" data-anim="fade-up">
                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition duration-500"
                     style="background: radial-gradient(600px circle at var(--x,50%) var(--y,50%), <?= e($link['color']) ?>26, transparent 40%)"></div>
                <div class="shrink-0 w-14 h-14 rounded-2xl grid place-items-center border border-white/10 group-hover:scale-110 transition duration-300"
                     style="color: <?= e($link['color']) ?>; background: <?= e($link['color']) ?>1a; box-shadow: 0 0 20px <?= e($link['color']) ?>33">
                    <?= $icons[$link['icon_class']] ?? $icons['link'] ?>
                </div>
                <div class="min-w-0">
                    <h3 class="font-display font-bold text-white text-lg group-hover:text-neon-cyan transition"><?= e($link['title']) ?></h3>
                    <p class="text-xs text-slate-500 truncate"><?= e(preg_replace('#^https?://(www\.)?#', '', $link['url'])) ?></p>
                </div>
                <span class="ml-auto text-slate-600 group-hover:text-white group-hover:translate-x-1 transition">→</span>
            </a>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-xs text-slate-600 mt-10" data-anim="fade-up">
        Powered by <span class="font-display font-bold text-slate-400">CHIPER<span class="text-neon-cyan">X</span></span> © <?= date('Y') ?>
    </p>
</section>

<script>
    // Efek glow mengikuti kursor pada setiap kartu
    document.querySelectorAll('.link-card').forEach(card => {
        card.addEventListener('mousemove', e => {
            const r = card.getBoundingClientRect();
            card.style.setProperty('--x', (e.clientX - r.left) + 'px');
            card.style.setProperty('--y', (e.clientY - r.top) + 'px');
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
