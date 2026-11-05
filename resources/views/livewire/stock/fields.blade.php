<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2"><label for="stock-name" class="field-label">Nama barang <span class="text-rose-700">*</span></label><input id="stock-name" wire:model="formName" class="field" required maxlength="255" autocomplete="off" placeholder="Contoh: Notebook A5 kraft"></div>
    <div><label for="stock-sku" class="field-label">SKU <span class="font-normal text-slate-500">(opsional)</span></label><input id="stock-sku" wire:model="formSku" class="field" maxlength="100" placeholder="ATK-001"></div>
    <div><label for="stock-unit" class="field-label">Satuan <span class="text-rose-700">*</span></label><input id="stock-unit" wire:model="formUnit" class="field" required maxlength="30" placeholder="pcs, box, kg"></div>
    <div><label for="stock-quantity" class="field-label">Jumlah stok <span class="text-rose-700">*</span></label><input id="stock-quantity" type="number" wire:model="formQuantity" class="field" required min="0" max="2147483647" step="1"></div>
    <div><label for="stock-minimum" class="field-label">Batas minimum <span class="text-rose-700">*</span></label><input id="stock-minimum" type="number" wire:model="formMinStock" class="field" required min="0" max="2147483647" step="1"></div>
    <div class="sm:col-span-2"><label for="stock-price" class="field-label">Harga per satuan (Rp) <span class="font-normal text-slate-500">(opsional)</span></label><input id="stock-price" type="number" wire:model="formPrice" class="field" min="0" step="0.01" max="9999999999.99" placeholder="0"></div>
    <div class="sm:col-span-2" x-data="markdownEditor($wire, 'formDescription')">
        <div class="flex items-center justify-between gap-3 mb-2"><label for="stock-description" class="field-label mb-0">Deskripsi Markdown</label><button type="button" @click="activate()" x-show="!editor" class="text-xs font-semibold text-emerald-800 underline underline-offset-4">Editor visual</button></div>
        <textarea id="stock-description" wire:model="formDescription" x-show="!editor" class="field min-h-32" maxlength="20000" rows="4" placeholder="Catatan, spesifikasi, atau **detail penting**"></textarea>
        <div x-ref="editor" wire:ignore x-show="editor" class="rounded-lg border border-stone-300"></div>
        <p class="mt-2 text-xs text-slate-500">Mendukung **tebal**, daftar, dan tautan. Status stok dihitung otomatis saat disimpan.</p>
    </div>
</div>
