<div>
    <div class="page-heading">
        <div><p class="eyebrow">INVENTORY OVERVIEW</p><h1 class="page-title">Semua barang, satu ruang.</h1><p class="page-subtitle">Pantau persediaan dan jaga aktivitas Anda tetap berjalan.</p></div>
        <button type="button" wire:click="openCreateModal" class="btn-primary"><span aria-hidden="true">＋</span> Tambah barang</button>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-8">
        <div class="stat-card"><span class="stat-label">Total jenis barang <span aria-hidden="true">▦</span></span><p class="stat-number">{{ number_format($summary->items) }}<span>jenis</span></p><p class="stat-note">{{ number_format($summary->units) }} unit dalam inventaris</p></div>
        <div class="stat-card"><span class="stat-label">Stok tersedia <span class="text-emerald-700" aria-hidden="true">●</span></span><p class="stat-number">{{ $counts['available'] ?? 0 }}<span>jenis</span></p><p class="stat-note">Persediaan di atas batas minimum</p></div>
        <div class="stat-card"><span class="stat-label">Perlu perhatian <span class="text-amber-700" aria-hidden="true">◉</span></span><p class="stat-number">{{ ($counts['low_stock'] ?? 0) + ($counts['out_of_stock'] ?? 0) }}<span>jenis</span></p><p class="stat-note">{{ $counts['low_stock'] ?? 0 }} menipis · {{ $counts['out_of_stock'] ?? 0 }} habis</p></div>
        <div class="stat-card bg-emerald-950! text-white"><span class="stat-label text-emerald-100!">Nilai persediaan <span aria-hidden="true">↗</span></span><p class="mt-4 text-2xl font-semibold tracking-tight">Rp {{ number_format($summary->value, 0, ',', '.') }}</p><p class="stat-note text-emerald-100!">Berdasarkan jumlah × harga satuan</p></div>
    </div>
    <section class="panel" aria-labelledby="inventory-heading">
        <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6 border-b border-stone-200">
            <div><h2 id="inventory-heading" class="text-lg font-semibold text-slate-900">Daftar inventaris <span class="count-pill">{{ $stocks->total() }}</span></h2><p class="text-xs text-slate-500 mt-1">Kelola barang dan jumlah persediaan Anda.</p></div>
            <div class="flex flex-wrap gap-2"><button type="button" wire:click="openAiModal" class="btn-ai"><span aria-hidden="true">✧</span> Bantu dengan AI</button><button type="button" wire:click="openImportModal" class="btn-secondary">Impor</button><button type="button" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel" class="btn-secondary">Ekspor Excel <span aria-hidden="true">↗</span></button></div>
        </div>
        <div class="p-5 sm:px-6 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-lg"><label class="sr-only" for="stock-search">Cari nama, SKU, atau deskripsi barang</label><span class="absolute top-3 left-3 text-slate-500" aria-hidden="true">⌕</span><input id="stock-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama, SKU, atau deskripsi..." class="field pl-9"></div>
            <div class="sm:ml-auto"><label class="sr-only" for="stock-status">Filter status stok</label><select id="stock-status" wire:model.live="statusFilter" class="field sm:w-48"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></div>
        </div>
        <x-errors />
        @include('livewire.stock.table', ['readOnly' => false])
        <div class="border-t border-stone-200 p-5">{{ $stocks->links() }}</div>
    </section>
    <p class="mt-5 text-xs text-slate-500 flex gap-2"><span aria-hidden="true">ⓘ</span> Stok menipis saat jumlah ≤ batas minimum. Stok habis saat jumlah 0.</p>

    @if($showFormModal)
        <x-modal id="stock-form" :title="$editingStockId ? 'Edit barang' : 'Tambah barang baru'" close="closeFormModal">
            <form wire:submit="saveStock"><x-errors />@include('livewire.stock.fields')
                <div class="modal-actions"><button type="button" wire:click="closeFormModal" class="btn-secondary">Batal</button><button type="submit" wire:loading.attr="disabled" class="btn-primary">Simpan barang</button></div>
            </form>
        </x-modal>
    @endif
    @if($confirmingStockDeletionId)
        <x-modal id="stock-delete" title="Hapus barang?" close="cancelDelete"><p class="text-sm text-slate-600">Data barang dan gambar akan dihapus. Tindakan ini tidak dapat dibatalkan.</p><div class="modal-actions"><button type="button" wire:click="cancelDelete" class="btn-secondary">Batal</button><button type="button" wire:click="deleteStock" wire:loading.attr="disabled" class="btn-danger">Hapus barang</button></div></x-modal>
    @endif
    @if($showImportModal)
        <x-modal id="stock-import" title="Impor inventaris" close="closeImportModal"><form wire:submit="importExcel"><x-errors /><p class="text-sm text-slate-600 mb-4">Gunakan hasil ekspor Excel sebagai template. Header wajib: <strong>name, sku, description, quantity, unit, price, min_stock</strong>. Nilai SKU, deskripsi, dan harga boleh kosong. Maksimal 1.000 baris dan 10 MB.</p><label for="import-file" class="field-label">File Excel atau CSV</label><input id="import-file" type="file" accept=".xlsx,.xls,.csv" wire:model="importFile" class="field" required><p class="text-xs text-slate-500 mt-3">Semua baris divalidasi sebelum disimpan. Status dihitung otomatis dan barang menjadi milik akun Anda.</p><div class="modal-actions"><button type="button" wire:click="closeImportModal" class="btn-secondary">Batal</button><button type="submit" class="btn-primary" wire:loading.attr="disabled">Impor barang</button></div></form></x-modal>
    @endif
    @if($showAiModal)
        <x-modal id="stock-ai" title="Mulai inventaris dengan AI" close="closeAiModal">
            <x-errors />
            <p class="text-sm text-slate-600 mb-5">Ceritakan kebutuhan Anda. Tinjau usulan barang, lalu pilih yang ingin disimpan.</p>
            <form wire:submit="generateWithAi"><label for="ai-prompt" class="field-label">Inventaris apa yang Anda siapkan?</label><textarea id="ai-prompt" wire:model="aiPrompt" class="field" rows="3" minlength="3" maxlength="500" required placeholder="Contoh: perlengkapan awal untuk kedai kopi kecil"></textarea><div class="mt-4 flex items-end gap-3"><div class="flex-1"><label for="ai-count" class="field-label">Jumlah usulan (1–10)</label><input id="ai-count" type="number" wire:model="aiCount" min="1" max="10" required class="field"></div><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="generateWithAi"><span wire:loading.remove wire:target="generateWithAi">Buat usulan</span><span wire:loading wire:target="generateWithAi" role="status">Membuat...</span></button></div></form>
            @if($aiErrorMessage)<p role="alert" class="mt-4 text-sm text-rose-800 bg-rose-50 rounded-lg p-3">{{ $aiErrorMessage }}</p>@endif
            @if($aiGeneratedItems)
                <fieldset class="mt-6"><legend class="field-label">Pilih barang yang akan disimpan</legend><div class="space-y-2">@foreach($aiGeneratedItems as $index => $item)<label class="flex gap-3 p-3 rounded-lg border border-stone-200" wire:key="ai-item-{{ $index }}"><input type="checkbox" wire:model="aiSelectedIndices" value="{{ $index }}" class="mt-1 rounded accent-emerald-800"><span><strong class="block text-sm">{{ $item['name'] }}</strong><span class="block text-xs text-slate-600 mt-1">{{ $item['quantity'] }} {{ $item['unit'] }} · {{ $item['sku'] ?? 'Tanpa SKU' }} · Rp {{ number_format($item['price'] ?? 0, 0, ',', '.') }}</span><span class="block text-xs text-slate-500 mt-1">{{ $item['description'] }}</span></span></label>@endforeach</div></fieldset>
                <p class="text-xs text-slate-500 mt-3">Periksa kembali jumlah dan harga usulan AI sebelum digunakan.</p>
                <div class="modal-actions"><button type="button" wire:click="closeAiModal" class="btn-secondary">Batal</button><button type="button" wire:click="saveAiGenerated" wire:loading.attr="disabled" class="btn-primary">Simpan pilihan</button></div>
            @endif
        </x-modal>
    @endif
</div>
