<?php /** Parsial form produk (dipakai modal tambah & edit) */ ?>
<input name="name" required maxlength="160" placeholder="Nama produk" class="form-input w-full text-sm">
<div class="grid grid-cols-2 gap-3">
    <select name="type" class="form-input text-sm">
        <option value="coin_redeem">🎁 Redeem (koin)</option>
        <option value="premium_store">🛒 Store (IDR)</option>
    </select>
    <input name="price" type="number" min="0" required placeholder="Harga (koin/IDR)" class="form-input text-sm">
</div>
<div class="grid grid-cols-2 gap-3">
    <input name="stock" type="number" min="0" placeholder="Stok (kosong = ∞)" class="form-input text-sm">
    <input name="file_url" maxlength="500" placeholder="https://… (URL file)" class="form-input text-sm">
</div>
<div>
    <label class="text-xs text-slate-500">Atau upload file project (zip/rar/pdf/dll, maks 50MB):</label>
    <input name="file" type="file" accept=".zip,.rar,.7z,.pdf,.txt,.md,.png,.jpg,.jpeg,.webp" class="form-input w-full text-xs mt-1 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-violet-600/30 file:text-violet-200 file:text-xs">
</div>
<textarea name="description" rows="2" maxlength="5000" placeholder="Deskripsi produk" class="form-input w-full text-sm"></textarea>
<div class="flex gap-5 text-sm">
    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_featured" class="accent-violet-500"> Featured</label>
    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_active" checked class="accent-violet-500"> Aktif</label>
</div>
