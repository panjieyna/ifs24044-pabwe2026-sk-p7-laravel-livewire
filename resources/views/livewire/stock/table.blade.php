<div class="overflow-x-auto">
<table class="inventory-table">
    <caption class="sr-only">{{ $readOnly ? 'Inventaris pengguna, hanya baca' : 'Barang inventaris milik Anda' }}</caption>
    <thead><tr><th scope="col">BARANG</th><th scope="col">STOK</th><th scope="col">HARGA SATUAN</th><th scope="col">STATUS</th><th scope="col">{{ $readOnly ? 'DETAIL' : 'AKSI' }}</th></tr></thead>
    <tbody>
        @forelse($stocks as $stock)
        @php($badge = \App\Features\Stock\Enums\StockStatus::from($stock->status))
        <tr wire:key="stock-{{ $stock->id }}">
            <td><div class="flex items-center gap-3">
                @if($stock->coverImageUrl())<img src="{{ $stock->coverImageUrl() }}" width="44" height="44" loading="lazy" alt="" class="h-11 w-11 rounded-lg object-cover bg-stone-100">
                @else<span class="item-placeholder" aria-hidden="true"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4 7 8 4 8-4M12 11v10M8 5l8 4"/></svg></span>@endif
                <div>@if($readOnly)<span class="font-semibold text-slate-900">{{ $stock->name }}</span>@else<a href="{{ route('stocks.show', $stock) }}" class="font-semibold text-slate-900 hover:text-emerald-800 hover:underline">{{ $stock->name }}</a>@endif<p class="mt-1 text-xs text-slate-500 font-mono">{{ $stock->sku ?: 'Tanpa SKU' }}</p></div>
            </div></td>
            <td><div class="flex items-center gap-2">@unless($readOnly)<button type="button" wire:click="adjustQuantity({{ $stock->id }}, -1)" @disabled($stock->quantity === 0) wire:loading.attr="disabled" class="quantity-button" aria-label="Kurangi stok {{ $stock->name }}">−</button>@endunless<span class="font-semibold tabular-nums">{{ number_format($stock->quantity) }} <span class="text-xs text-slate-500 font-normal">{{ $stock->unit }}</span></span>@unless($readOnly)<button type="button" wire:click="adjustQuantity({{ $stock->id }}, 1)" wire:loading.attr="disabled" class="quantity-button" aria-label="Tambah stok {{ $stock->name }}">＋</button>@endunless</div><p class="mt-1 text-xs text-slate-500">Min. {{ $stock->min_stock }} {{ $stock->unit }}</p></td>
            <td class="tabular-nums text-slate-600">{{ $stock->price === null ? '—' : 'Rp '.number_format($stock->price, 2, ',', '.') }}</td>
            <td><span class="status-badge {{ $badge->badgeClass() }}"><span aria-hidden="true">●</span> {{ $badge->label() }}</span></td>
            <td>@if($readOnly)<details><summary class="cursor-pointer text-emerald-800 text-sm">Deskripsi</summary><div class="prose-stock mt-2 max-w-sm">{!! \Illuminate\Support\Str::markdown($stock->description ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div></details>@else<div class="flex items-center gap-1"><button type="button" wire:click="openEditModal({{ $stock->id }})" class="btn-icon" aria-label="Edit {{ $stock->name }}" title="Edit barang"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m15 4 5 5M4 20l5-1L21 7l-5-5L4 14v6Z"/></svg></button><button type="button" wire:click="confirmDelete({{ $stock->id }})" class="btn-icon text-rose-700 hover:bg-rose-50" aria-label="Hapus {{ $stock->name }}" title="Hapus barang"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7m4-7v7"/></svg></button></div>@endif</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center py-16!"><div class="mx-auto item-placeholder mb-4" aria-hidden="true">▦</div><p class="font-semibold text-slate-800">{{ $search || $statusFilter ? 'Tidak ada barang yang cocok' : 'Ruang untuk barang pertama Anda' }}</p><p class="text-sm text-slate-500 mt-2">{{ $search || $statusFilter ? 'Coba kata kunci atau status lainnya.' : 'Tambahkan barang, impor Excel, atau mulai dengan bantuan AI.' }}</p></td></tr>
        @endforelse
    </tbody>
</table>
</div>
