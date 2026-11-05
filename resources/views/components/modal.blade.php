@props(['id', 'title', 'close'])
<div x-data="{ previous: null, init() { this.previous = document.activeElement }, destroy() { this.previous?.focus() } }" x-trap.inert.noscroll="true" @keydown.escape.window.prevent="$wire.{{ $close }}()" class="modal-overlay" @click.self="$wire.{{ $close }}()">
    <section role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" class="modal-panel" tabindex="-1">
        <div class="flex items-start justify-between gap-4 border-b border-stone-200 p-6"><h2 id="{{ $id }}-title" class="text-xl font-semibold text-emerald-950">{{ $title }}</h2><button type="button" wire:click="{{ $close }}" class="btn-icon" aria-label="Tutup dialog">✕</button></div>
        <div class="p-6">{{ $slot }}</div>
    </section>
</div>
