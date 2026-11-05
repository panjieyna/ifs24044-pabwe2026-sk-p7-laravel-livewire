<?php

namespace App\Features\Auth\Livewire;

use App\Features\Auth\Actions\LoginUserAction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Masuk | Ruang Stok')]
class Login extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ];
    }

    public function authenticate(LoginUserAction $action): mixed
    {
        $this->validate();

        $key = 'login:'.hash('sha256', mb_strtolower($this->login).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('login', 'Terlalu banyak percobaan masuk. Coba lagi dalam satu menit.');

            return null;
        }

        if ($action->execute($this->login, $this->password, $this->remember)) {
            RateLimiter::clear($key);
            $user = Auth::user();
            if ($user && $user->isAdmin()) {
                return $this->redirectRoute('admin.users');
            }

            return $this->redirectRoute('stocks.index');
        }

        RateLimiter::hit($key, 60);
        $this->addError('login', 'Username/email atau password yang Anda masukkan tidak sesuai.');

        return null;
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
