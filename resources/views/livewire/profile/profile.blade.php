<div>
    <header class="mb-8">
        <p class="text-sm font-semibold text-teal-700 mb-2">AKUN PRIBADI</p>
        <h1 class="text-3xl font-bold text-slate-900">Pengaturan Profil</h1>
        <p class="text-slate-600 mt-2">Kelola identitas, foto profil, dan keamanan akun Anda.</p>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section aria-labelledby="photo-heading" class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 id="photo-heading" class="text-lg font-semibold mb-5">Foto profil</h2>
            <img src="{{ $user->profilePhotoUrl() }}" alt="Foto profil {{ $user->name }}" width="128" height="128" class="w-32 h-32 rounded-2xl object-cover mb-5">
            <label for="profile-photo" class="block text-sm font-semibold mb-2">Pilih foto baru</label>
            <input id="profile-photo" type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp,image/gif" aria-describedby="photo-help" class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:text-teal-800">
            <p id="photo-help" class="text-sm text-slate-600 mt-3">PNG, JPG, WEBP, atau GIF, maksimal 5 MB. Foto dipotong di tengah menjadi 400 ? 400 piksel.</p>
            @error('photo') <p role="alert" class="text-sm text-red-700 mt-2">{{ $message }}</p> @enderror
            @error('croppedPhoto') <p role="alert" class="text-sm text-red-700 mt-2">{{ $message }}</p> @enderror
            <p wire:loading wire:target="photo,saveCroppedPhoto" role="status" class="mt-3 text-sm text-teal-800">Memproses foto?</p>
            @if ($user->profile_photo_path)
                <button type="button" wire:click="confirmDeletePhoto" class="mt-5 text-sm font-semibold text-red-700 underline underline-offset-4">Hapus foto profil</button>
            @endif
        </section>

        <div class="lg:col-span-2 space-y-6">
            <section aria-labelledby="identity-heading" class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 id="identity-heading" class="text-lg font-semibold mb-5">Identitas diri</h2>
                <form wire:submit="updateProfileInformation" class="space-y-4">
                    <div>
                        <label for="profile-name" class="block text-sm font-medium mb-2">Nama lengkap</label>
                        <input id="profile-name" wire:model="name" autocomplete="name" type="text" maxlength="255" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        @error('name') <p role="alert" class="text-sm text-red-700 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="profile-username" class="block text-sm font-medium mb-2">Username</label>
                            <input id="profile-username" wire:model="username" autocomplete="username" type="text" minlength="3" maxlength="50" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            @error('username') <p role="alert" class="text-sm text-red-700 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="profile-email" class="block text-sm font-medium mb-2">Email</label>
                            <input id="profile-email" wire:model="email" autocomplete="email" type="email" maxlength="255" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            @error('email') <p role="alert" class="text-sm text-red-700 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-teal-800 hover:bg-teal-900 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">Simpan perubahan</button>
                </form>
            </section>

            <section aria-labelledby="password-heading" class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 id="password-heading" class="text-lg font-semibold mb-5">Keamanan akun</h2>
                <form wire:submit="updatePassword" class="space-y-4">
                    <div>
                        <label for="profile-current-password" class="block text-sm font-medium mb-2">Password saat ini</label>
                        <input id="profile-current-password" wire:model="current_password" autocomplete="current-password" type="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        @error('current_password') <p role="alert" class="text-sm text-red-700 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="profile-new-password" class="block text-sm font-medium mb-2">Password baru</label>
                            <input id="profile-new-password" wire:model="password" autocomplete="new-password" type="password" minlength="8" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                            @error('password') <p role="alert" class="text-sm text-red-700 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="profile-password-confirmation" class="block text-sm font-medium mb-2">Konfirmasi password baru</label>
                            <input id="profile-password-confirmation" wire:model="password_confirmation" autocomplete="new-password" type="password" minlength="8" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                        </div>
                    </div>
                    <p class="text-sm text-slate-600">Gunakan minimal 8 karakter.</p>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-teal-800 hover:bg-teal-900 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">Perbarui password</button>
                </form>
            </section>
        </div>
    </div>

    @if ($showCropModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.closeCropModal()">
            <section role="dialog" aria-modal="true" aria-labelledby="crop-heading" class="w-full max-w-md bg-white rounded-2xl p-6">
                <h2 id="crop-heading" class="text-xl font-semibold">Gunakan foto ini?</h2>
                <p class="text-sm text-slate-600 mt-2">Bagian tengah foto akan dipotong menjadi persegi.</p>
                @if ($photo)
                    <img src="{{ $photo->temporaryUrl() }}" width="240" height="240" alt="Pratinjau foto profil baru" class="w-60 h-60 object-cover rounded-xl mx-auto my-5">
                @endif
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" wire:click="closeCropModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Batal</button>
                    <button type="button" wire:click="saveCroppedPhoto" wire:loading.attr="disabled" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white">Simpan foto</button>
                </div>
            </section>
        </div>
    @endif

    @if ($confirmingPhotoDeletion)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" x-data x-trap.inert.noscroll="true" @keydown.escape.window="$wire.cancelDeletePhoto()">
            <section role="dialog" aria-modal="true" aria-labelledby="delete-photo-heading" class="w-full max-w-md bg-white rounded-2xl p-6">
                <h2 id="delete-photo-heading" class="text-xl font-semibold">Hapus foto profil?</h2>
                <p class="text-sm text-slate-600 mt-2">Foto akan dihapus dan diganti dengan inisial nama Anda.</p>
                <div class="flex justify-end gap-3 mt-5">
                    <button type="button" wire:click="cancelDeletePhoto" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Batal</button>
                    <button type="button" wire:click="deletePhoto" wire:loading.attr="disabled" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Hapus foto</button>
                </div>
            </section>
        </div>
    @endif
</div>
