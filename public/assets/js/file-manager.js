/* ============================================================
   ChiperX — File Manager (Owner): tree explorer + Monaco Editor
   ============================================================ */
'use strict';

(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const treeEl = document.getElementById('fileTree');
    const currentEl = document.getElementById('currentFile');
    const metaEl = document.getElementById('fileMeta');
    const saveBtn = document.getElementById('saveBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    let editor = null;
    let currentPath = null;
    let pendingOpen = null; // file yang diklik sebelum Monaco siap

    // ---------- Monaco bootstrap ----------
    require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' } });
    require(['vs/editor/editor.main'], function () {
        monaco.editor.defineTheme('chiperx', {
            base: 'vs-dark',
            inherit: true,
            rules: [{ background: '0f172a' }],
            colors: {
                'editor.background': '#0b1120',
                'editor.lineHighlightBackground': '#1e293b55',
                'editorLineNumber.foreground': '#334155',
            },
        });
        editor = monaco.editor.create(document.getElementById('monacoContainer'), {
            value: '// Pilih file di panel kiri untuk mulai mengedit…\n// Semua perubahan di-backup otomatis & dicatat ke Discord.\n',
            language: 'plaintext',
            theme: 'chiperx',
            fontSize: 13,
            minimap: { enabled: true },
            automaticLayout: true,
            scrollBeyondLastLine: false,
            tabSize: 4,
            renderWhitespace: 'selection',
        });
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, saveFile);
        if (pendingOpen) {
            const p = pendingOpen;
            pendingOpen = null;
            openFile(p);
        }
    });

    // ---------- Helper fetch ----------
    async function api(url, options = {}) {
        const res = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                ...(options.headers || {}),
            },
        });
        return res.json();
    }

    function toast(message, ok = true) {
        const el = document.createElement('div');
        el.className = 'fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl border text-sm backdrop-blur-xl shadow-xl ' +
            (ok ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-300' : 'bg-red-500/15 border-red-400/30 text-red-300');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(() => el.remove(), 400); }, 2600);
    }

    // ---------- Directory tree ----------
    function iconFor(item) {
        if (item.type === 'dir') return '📂';
        const ext = item.name.split('.').pop().toLowerCase();
        const map = { php: '🐘', js: '🟨', css: '🎨', html: '📄', json: '🧾', sql: '🗄️', md: '📝', env: '🔐', yml: '⚙️', yaml: '⚙️' };
        return map[ext] ?? '📄';
    }

    function renderItems(container, items) {
        items.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'tree-item' + (item.path === currentPath ? ' active' : '');
            row.dataset.path = item.path;
            row.innerHTML = `<span>${iconFor(item)}</span><span class="truncate">${item.name}</span>` +
                (item.type === 'file' ? `<span class="ml-auto text-[9px] text-slate-600">${item.size ? Math.round(item.size / 1024) + 'K' : '0K'}</span>` : '');
            row.addEventListener('click', (e) => {
                e.stopPropagation();
                if (item.type === 'dir') {
                    const kids = row.nextElementSibling;
                    if (kids && kids.classList.contains('tree-children')) kids.classList.toggle('hidden');
                } else {
                    openFile(item.path);
                }
            });
            container.appendChild(row);
            if (item.type === 'dir' && item.children) {
                const kids = document.createElement('div');
                kids.className = 'tree-children hidden';
                renderItems(kids, item.children);
                container.appendChild(kids);
            }
        });
    }

    window.loadTree = async function (force = false) {
        treeEl.innerHTML = '<p class="p-3 text-slate-500">Memuat struktur folder…</p>';
        const data = await api(window.FM_API.tree);
        if (!data.ok) { treeEl.innerHTML = `<p class="p-3 text-red-400">${data.message}</p>`; return; }
        treeEl.innerHTML = '';
        renderItems(treeEl, data.tree);
    };

    // ---------- File actions ----------
    window.openFile = async function (path) {
        if (!editor) {
            pendingOpen = path;
            currentEl.textContent = 'Menyiapkan editor…';
            return;
        }
        currentEl.textContent = 'Memuat ' + path + ' …';
        const data = await api(window.FM_API.read + '?path=' + encodeURIComponent(path));
        if (!data.ok) { toast(data.message, false); currentEl.textContent = currentPath ?? '—'; return; }
        currentPath = path;
        currentEl.textContent = path;
        metaEl.textContent = Math.round(data.size / 1024) + ' KB' + (data.readonly ? ' · READ-ONLY' : '');
        saveBtn.disabled = data.readonly;
        deleteBtn.classList.remove('hidden');
        if (editor) {
            const model = editor.getModel();
            monaco.editor.setModelLanguage(model, data.language);
            model.setValue(data.content);
        }
        document.querySelectorAll('.tree-item').forEach((t) => t.classList.toggle('active', t.dataset.path === path));
    };

    window.saveFile = async function () {
        if (!editor || !currentPath) return;
        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ Menyimpan…';
        const data = await api(window.FM_API.save, {
            method: 'POST',
            body: JSON.stringify({ path: currentPath, content: editor.getValue() }),
        });
        saveBtn.disabled = false;
        saveBtn.textContent = '💾 Simpan (Ctrl+S)';
        toast(data.message, data.ok);
    };

    window.createItem = async function (kind) {
        const path = prompt(kind === 'dir' ? 'Path folder baru (mis: public/assets/js/baru):' : 'Path file baru (mis: src/Services/FiturBaru.php):');
        if (!path) return;
        const data = await api(window.FM_API.create, { method: 'POST', body: JSON.stringify({ path, kind }) });
        toast(data.message, data.ok);
        if (data.ok) loadTree(true);
    };

    window.deleteCurrent = async function () {
        if (!currentPath || !confirm('Hapus "' + currentPath + '"? Backup otomatis dibuat.')) return;
        const data = await api(window.FM_API.delete, { method: 'POST', body: JSON.stringify({ path: currentPath }) });
        toast(data.message, data.ok);
        if (data.ok) {
            currentPath = null;
            currentEl.textContent = '— belum ada file dibuka —';
            saveBtn.disabled = true;
            deleteBtn.classList.add('hidden');
            editor?.getModel().setValue('// File telah dihapus.\n');
            loadTree(true);
        }
    };

    loadTree();
})();
