<?php

namespace App\Features\Profile\Livewire;

use App\Features\Profile\Services\ProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('components.layouts.app')]
#[Title('Profil Saya | Ruang Stok')]
class Profile extends Component
{
    use WithFileUploads;

    // Profile Information
    public string $name = '';

    public string $username = '';

    public string $email = '';

    // Password Update
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Photo Crop State
    public mixed $photo = null;

    public string $croppedPhoto = '';

    public bool $showCropModal = false;

    public bool $confirmingPhotoDeletion = false;

    public function boot(): void
    {
        abort_unless(Auth::check(), 401);
    }

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
    }

    public function updatedPhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        $this->showCropModal = true;
    }

    public function closeCropModal(): void
    {
        $this->showCropModal = false;
        $this->photo = null;
        $this->croppedPhoto = '';
    }

    public function updateProfileInformation(ProfileService $service): void
    {
        $user = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $service->updateProfile($user, [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
        ]);

        session()->flash('success', 'Identitas profil berhasil diperbarui.');
    }

    public function updatePassword(ProfileService $service): void
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini yang Anda masukkan salah.');

            return;
        }

        $service->updatePassword($user, $this->password);
        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('success', 'Password akun berhasil diubah.');
    }

    public function saveCroppedPhoto(ProfileService $service): void
    {
        if (empty($this->croppedPhoto)) {
            if ($this->photo) {
                $this->validate(['photo' => ['required', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000']]);
                $imageData = file_get_contents($this->photo->getRealPath());
            } else {
                $this->addError('photo', 'Pilih foto profil terlebih dahulu.');

                return;
            }
        } else {
            $this->validate(['croppedPhoto' => ['string', 'max:7000000', 'regex:/^data:image\/(png|jpeg|webp|gif);base64,[A-Za-z0-9+\/=]+$/']]);
            $imageData = $this->croppedPhoto;
        }

        $user = Auth::user();
        try {
            $service->updateProfilePhoto($user, $imageData);
        } catch (RuntimeException $exception) {
            $this->addError('photo', $exception->getMessage());

            return;
        }

        $this->closeCropModal();
        session()->flash('success', 'Foto profil (rasio 1:1) berhasil disimpan.');
    }

    public function confirmDeletePhoto(): void
    {
        $this->confirmingPhotoDeletion = true;
    }

    public function cancelDeletePhoto(): void
    {
        $this->confirmingPhotoDeletion = false;
    }

    public function deletePhoto(ProfileService $service): void
    {
        $user = Auth::user();
        $service->deleteProfilePhoto($user);
        $this->confirmingPhotoDeletion = false;

        session()->flash('success', 'Foto profil berhasil dihapus.');
    }

    public function render(): View
    {
        return view('livewire.profile.profile', [
            'user' => Auth::user(),
        ]);
    }
}
