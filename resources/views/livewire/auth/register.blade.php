<div class="login-shell register-shell">
    <aside class="login-story" aria-label="Tentang Ruang Stok" style="background-image: url('{{ asset('images/register-inventory-green.webp') }}')">
        <div class="login-story-copy">
            <p class="login-eyebrow">MULAI LEBIH TERATUR</p>
            <p class="login-story-title">Ruang baru untuk<br><span>persediaan Anda.</span></p>
            <p class="login-story-description">Daftar dan kelola inventaris dalam satu tempat.</p>
        </div>
    </aside>

    <section class="login-panel register-panel" aria-labelledby="register-heading">
        <div class="login-form-content register-form-content">
            <a href="{{ route('home') }}" class="login-brand">
                <svg width="38" height="38" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                    <rect width="40" height="40" rx="12" fill="#d9eea4"/>
                    <path d="m11 14 9-5 9 5v12l-9 5-9-5V14Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="m11 14 9 5 9-5M20 19v12m-5-19 9 5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span>ruang<span>stok.</span></span>
            </a>

            <div class="login-heading">
                <p class="login-eyebrow">MULAI RUANG KERJA ANDA</p>
                <h1 id="register-heading">Buat Akun Member</h1>
                <p>Sudah memiliki akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
            </div>

            <form wire:submit.prevent="register" class="login-form register-form">
                <div class="register-fields">
                    <div>
                        <label for="name" class="login-label">Nama lengkap</label>
                        <input wire:model="name" type="text" id="name" autocomplete="name" maxlength="255" class="login-input" placeholder="Nama Anda" @error('name') aria-invalid="true" aria-describedby="register-name-error" @enderror required>
                        @error('name') <span id="register-name-error" role="alert" class="login-error">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="username" class="login-label">Username</label>
                        <input wire:model="username" type="text" id="username" autocomplete="username" minlength="3" maxlength="50" autocapitalize="none" spellcheck="false" class="login-input" placeholder="Username" @error('username') aria-invalid="true" aria-describedby="register-username-error" @enderror required>
                        @error('username') <span id="register-username-error" role="alert" class="login-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="register-email-field">
                        <label for="email" class="login-label">Email</label>
                        <input wire:model="email" type="email" id="email" autocomplete="email" maxlength="255" autocapitalize="none" spellcheck="false" class="login-input" placeholder="nama@email.com" @error('email') aria-invalid="true" aria-describedby="register-email-error" @enderror required>
                        @error('email') <span id="register-email-error" role="alert" class="login-error">{{ $message }}</span> @enderror
                    </div>

                    @foreach ([
                        'password' => ['label' => 'Password', 'placeholder' => 'Minimal 8 karakter', 'toggle' => 'register-password-toggle', 'accessible' => 'password'],
                        'password_confirmation' => ['label' => 'Konfirmasi password', 'placeholder' => 'Ulangi password', 'toggle' => 'register-confirmation-toggle', 'accessible' => 'konfirmasi password'],
                    ] as $fieldName => $field)
                        <div x-data="{ showPassword: false }" class="register-password-field">
                            <label for="{{ $fieldName }}" class="login-label">{{ $field['label'] }}</label>
                            <div class="login-input-wrap">
                                <input wire:model="{{ $fieldName }}" type="password" :type="showPassword ? 'text' : 'password'" id="{{ $fieldName }}" autocomplete="new-password" minlength="8" class="login-input login-password-input" placeholder="{{ $field['placeholder'] }}" @error($fieldName) aria-invalid="true" aria-describedby="register-{{ $fieldName }}-error" @enderror required>
                                <button id="{{ $field['toggle'] }}" type="button" class="login-password-toggle" @click="showPassword = !showPassword" aria-label="Tampilkan {{ $field['accessible'] }}" :aria-label="showPassword ? 'Sembunyikan {{ $field['accessible'] }}' : 'Tampilkan {{ $field['accessible'] }}'" aria-controls="{{ $fieldName }}" aria-pressed="false" :aria-pressed="showPassword">
                                    <svg x-show="!showPassword" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg x-show="showPassword" x-cloak width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.1 4M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.8M10 10a3 3 0 0 0 4 4" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                            @error($fieldName) <span id="register-{{ $fieldName }}-error" role="alert" class="login-error">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>

                <p class="register-password-help">Gunakan minimal 8 karakter untuk password Anda.</p>

                <button id="register-submit" type="submit" wire:loading.attr="disabled" class="login-submit">
                    <svg aria-hidden="true" wire:loading wire:target="register" class="animate-spin" width="18" height="18" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8H4Z"/>
                    </svg>
                    <span wire:loading.remove wire:target="register">Daftar Akun</span>
                    <span wire:loading wire:target="register">Mendaftarkan...</span>
                    <svg wire:loading.remove wire:target="register" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </div>
        <p class="login-footer">&copy; {{ now()->year }} Ruang Stok <span aria-hidden="true">&middot;</span> Manajemen inventaris</p>
    </section>
</div>
