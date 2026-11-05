<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_root_redirects_member_to_stocks(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/')
            ->assertRedirect(route('stocks.index'));
    }

    public function test_root_redirects_admin_to_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('admin.users'));
    }

    public function test_health_check_endpoint(): void
    {
        $this->get('/up')
            ->assertStatus(200);
    }

    public function test_authenticated_members_are_redirected_away_from_guest_pages(): void
    {
        $this->actingAs(User::factory()->member()->create());
        $this->get('/login')->assertRedirect(route('stocks.index'));
        $this->get('/register')->assertRedirect(route('stocks.index'));
    }

    public function test_authenticated_admins_are_redirected_away_from_guest_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/login')->assertRedirect(route('admin.users'));
    }
}
