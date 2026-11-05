<?php

namespace Tests\Feature\Admin;

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_forbidden_from_admin_pages(): void
    {
        $member = User::factory()->member()->create();
        $targetUser = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/admin/users')
            ->assertStatus(403);

        $this->actingAs($member)
            ->get("/admin/users/{$targetUser->id}/stocks")
            ->assertStatus(403);
    }

    public function test_admin_can_view_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@test.com']);
        User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@test.com']);

        $this->actingAs($admin);

        Livewire::test(UserList::class)
            ->assertSee('Budi Santoso')
            ->assertSee('Citra Lestari')
            ->set('search', 'Budi')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Lestari');
    }

    public function test_admin_can_view_user_stocks_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Dewi Sartika']);

        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Tugas Matematika', 'quantity' => 10, 'min_stock' => 5]);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Tugas Fisika', 'quantity' => 0, 'min_stock' => 5]);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->assertSee('Dewi Sartika')
            ->assertSee('Tugas Matematika')
            ->assertSee('Tugas Fisika')
            ->set('search', 'Matematika')
            ->assertSee('Tugas Matematika')
            ->assertDontSee('Tugas Fisika')
            ->set('search', '')
            ->set('statusFilter', 'out_of_stock')
            ->assertSee('Tugas Fisika')
            ->assertDontSee('Tugas Matematika');
    }

    public function test_admin_can_export_user_stocks(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create(['name' => 'Eko Prasetyo']);
        Stock::factory()->create(['user_id' => $member->id, 'name' => 'Tugas Export Admin']);

        $this->actingAs($admin);

        Livewire::test(AdminUserStockList::class, ['user' => $member])
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_member_cannot_mount_admin_components_directly(): void
    {
        $member = User::factory()->member()->create();
        $this->actingAs($member);
        Livewire::test(UserList::class)->assertForbidden();
        Livewire::test(AdminUserStockList::class, ['user' => $member])->assertForbidden();
    }

    public function test_admin_role_is_checked_again_on_component_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();
        $this->actingAs($admin);
        $component = Livewire::test(AdminUserStockList::class, ['user' => $member]);
        $admin->update(['role' => 'member']);
        $component->set('search', 'anything')->assertForbidden();
    }

    public function test_admin_target_user_cannot_be_changed_from_browser(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $component = Livewire::test(AdminUserStockList::class, ['user' => $first]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('user', $other);
    }

    public function test_admin_endpoints_do_not_accept_write_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id]);
        $this->actingAs($admin)
            ->delete('/admin/users/'.$member->id.'/stocks')
            ->assertStatus(405);
        $this->assertModelExists($stock);
        $this->actingAs($admin)->get('/stocks')->assertForbidden();
        $this->get('/stocks/'.$stock->id)->assertForbidden();
    }
}
