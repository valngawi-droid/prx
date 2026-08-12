<?php /** FILE MANAGER — directory tree + Monaco Editor (Owner God Mode) */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-white">🗂️ File Manager <span class="text-xs font-normal text-red-400 border border-red-500/40 rounded-full px-2.5 py-1 ml-2">GOD MODE</span></h1>
        <p class="text-xs text-slate-500 mt-1">Setiap perubahan otomatis di-backup & dilaporkan ke Discord.</p>
    </div>
    <div class="flex gap-2">
        <button onclick="createItem('file')" class="px-4 py-2 rounded-xl bg-white/10 border border-white/15 text-xs font-semibold hover:bg-white/15">＋ File</button>
        <button onclick="createItem('dir')" class="px-4 py-2 rounded-xl bg-white/10 border border-white/15 text-xs font-semibold hover:bg-white/15">＋ Folder</button>
        <button id="saveBtn" onclick="saveFile()" disabled class="px-5 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-cyan-500 text-xs font-bold text-white disabled:opacity-40">💾 Simpan (Ctrl+S)</button>
    </div>
</div>

<div class="grid lg:grid-cols-[280px,1fr] gap-4 mt-6" style="height: calc(100vh - 15rem); min-height: 480px;">
    <!-- TREE -->
    <div class="glass-card overflow-hidden flex flex-col">
        <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-400">📁 PROJECT ROOT</span>
            <button onclick="loadTree(true)" class="text-xs text-slate-500 hover:text-white" title="Muat ulang">⟳</button>
        </div>
        <div id="fileTree" class="flex-1 overflow-auto p-2 text-xs font-mono"></div>
    </div>
    <!-- EDITOR -->
    <div class="glass-card overflow-hidden flex flex-col">
        <div class="px-4 py-2.5 border-b border-white/10 flex items-center gap-3">
            <span id="currentFile" class="text-xs font-mono text-neon-cyan truncate">— belum ada file dibuka —</span>
            <span id="fileMeta" class="ml-auto text-[10px] text-slate-600"></span>
            <button id="deleteBtn" onclick="deleteCurrent()" class="hidden px-3 py-1 rounded-lg bg-red-500/15 text-red-300 text-[10px] hover:bg-red-500/25">🗑️ Hapus</button>
        </div>
        <div id="monacoContainer" class="flex-1"></div>
    </div>
</div>

<!-- Monaco Editor loader (CDN resmi Microsoft) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
<script>
    window.FM_API = {
        tree:   '/owner/api/files/tree',
        read:   '/owner/api/files/read',
        save:   '/owner/api/files/save',
        create: '/owner/api/files/create',
        delete: '/owner/api/files/delete',
    };
</script>
<script src="<?= asset('js/file-manager.js') ?>" defer></script>
