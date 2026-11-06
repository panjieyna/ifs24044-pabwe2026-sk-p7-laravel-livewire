<div class="login-shell">
    <aside class="login-story" aria-label="Tentang Ruang Stok" style="background-image: url('{{ asset('images/login-inventory-green.webp') }}')">
        <div class="login-story-copy">
            <p class="login-eyebrow">RUANG UNTUK LEBIH TERATUR</p>
            <p class="login-story-title">Persediaan tertata,<br><span>kerja lebih mudah.</span></p>
            <p class="login-story-description">Catat barang dan pantau persediaan.</p>
        </div>
    </aside>

    <section class="login-panel" aria-labelledby="login-heading">
        <div class="login-form-content">
            <a href="{{ route('home') }}" class="login-brand">
                <svg width="38" height="38" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                    <rect width="40" height="40" rx="12" fill="#d9eea4"/>
                    <path d="m11 14 9-5 9 5v12l-9 5-9-5V14Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="m11 14 9 5 9-5M20 19v12m-5-19 9 5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span>ruang<span>stok.</span></span>
            </a>

            <div class="login-heading">
                <p class="login-eyebrow">SELAMAT DATANG KEMBALI</p>
                <h1 id="login-heading">Masuk ke Akun Anda</h1>
                <p>Belum memiliki akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
            </div>

            <form wire:submit.prevent="authenticate" class="login-form">
                <div>
                    <label for="login-username" class="login-label">Username atau Email</label>
                    <div class="login-input-wrap">
                        <svg class="login-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <circle cx="12" cy="8" r="3.5"/>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2" stroke-linecap="round"/>
                        </svg>
                        <input wire:model="login" type="text" id="login-username" autocomplete="username" maxlength="255" class="login-input" placeholder="Username atau email" @error('login') aria-invalid="true" aria-describedby="login-username-error" @enderror required>
                    </div>
                    @error('login') <span id="login-username-error" role="alert" class="login-error">{{ $message }}</span> @enderror
                </div>

                <div x-data="{ showPassword: false }">
                    <label for="login-password" class="login-label">Password</label>
                    <div class="login-input-wrap">
                        <svg class="login-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="11" rx="2.5"/>
                            <path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v2" stroke-linecap="round"/>
                        </svg>
                        <input wire:model="password" type="password" :type="showPassword ? 'text' : 'password'" id="login-password" autocomplete="current-password" maxlength="1024" class="login-input login-password-input" placeholder="Masukkan password" @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror required>
                        <button id="login-password-toggle" type="button" class="login-password-toggle" @click="showPassword = !showPassword" aria-label="Tampilkan password" :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" aria-controls="login-password" aria-pressed="false" :aria-pressed="showPassword">
                            <svg x-show="!showPassword" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg x-show="showPassword" x-cloak width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.1 4M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.8M10 10a3 3 0 0 0 4 4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    @error('password') <span id="login-password-error" role="alert" class="login-error">{{ $message }}</span> @enderror
                </div>

                <label class="login-remember">
                    <input wire:model="remember" type="checkbox">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button id="login-submit" type="submit" wire:loading.attr="disabled" class="login-submit">
                    <svg aria-hidden="true" wire:loading wire:target="authenticate" class="animate-spin" width="18" height="18" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8H4Z"/>
                    </svg>
                    <span wire:loading.remove wire:target="authenticate">Masuk</span>
                    <span wire:loading wire:target="authenticate">Memverifikasi...</span>
                    <svg wire:loading.remove wire:target="authenticate" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>

            <div class="login-note">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3Z" stroke-linejoin="round"/>
                    <path d="m8.5 12 2.5 2.5 4.5-5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p>Inventaris tertata.<br>Aktivitas lebih bermakna.</p>
            </div>
        </div>
        <p class="login-footer">&copy; {{ now()->year }} Ruang Stok <span aria-hidden="true">&middot;</span> Manajemen inventaris</p>
    </section>
</div>
