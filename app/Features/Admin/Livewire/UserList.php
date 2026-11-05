<?php

namespace App\Features\Admin\Livewire;

use App\Features\Admin\Services\AdminUserService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Daftar Pengguna | Ruang Stok')]
class UserList extends Component
{
    use WithPagination;

    public string $search = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function boot(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(AdminUserService $adminService): View
    {
        $users = $adminService->getUsersList($this->search ?: null, 10);

        return view('livewire.admin.user-list', [
            'users' => $users,
        ]);
    }
}
