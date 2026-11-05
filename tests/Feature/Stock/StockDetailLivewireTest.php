<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stock_detail(): void
    {
        $stock = Stock::factory()->create();

        $this->get(route('stocks.show', $stock->id))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_access_other_member_stock(): void
    {
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();

        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherMember);

        $this->get(route('stocks.show', $stock->id))
            ->assertStatus(403);
    }

    public function test_member_can_view_own_stock_detail(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Tugas Utama Pro',
            'description' => '# Deskripsi Markdown',
            'quantity' => 20,
            'sku' => 'DETAIL-001',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSet('stock.name', 'Tugas Utama Pro')
            ->assertSet('stock.description', '# Deskripsi Markdown')
            ->assertSet('stock.status', 'available')
            ->assertSet('stock.sku', 'DETAIL-001')
            ->assertSee('Tugas Utama Pro');
    }

    public function test_open_and_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Judul Detail',
            'description' => 'Deskripsi lama',
            'quantity' => 20,
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('showEditModal', false)
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('formName', 'Judul Detail')
            ->assertSet('formDescription', 'Deskripsi lama')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_update_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Judul Awal']);

        $this->actingAs($user);

        // Validation errors
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('formName', 'a')
            ->set('formQuantity', -1)
            ->set('formMinStock', -1)
            ->set('formUnit', '')
            ->set('formPrice', '-1.123')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formMinStock', 'formUnit', 'formPrice']);

        // Success
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('formName', 'Judul Telah Diperbarui')
            ->set('formDescription', '## Rincian Baru')
            ->set('formQuantity', 3)
            ->set('formMinStock', 5)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Judul Telah Diperbarui',
            'description' => '## Rincian Baru',
            'status' => 'low_stock',
        ]);
    }

    public function test_adjust_quantity(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 5]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', 1)
            ->assertSet('stock.status', 'available')
            ->assertSet('stock.quantity', 6);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'status' => 'available',
        ]);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'cover_image_path' => null]);

        $this->actingAs($user);

        // Invalid file
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $invalidFile)
            ->assertHasErrors(['coverPhoto']);

        // Valid image upload
        $imageFile = UploadedFile::fake()->image('cover.jpg', 800, 400);
        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $imageFile)
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);

        // Delete cover photo
        $component->call('deleteCoverPhoto');

        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
    }

    public function test_delete_stock_redirects(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_modal_actions(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('confirmingStockDeletion', false)
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false)
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false);
    }

    public function test_admin_cannot_use_member_detail_component(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(StockDetail::class, ['stock' => Stock::factory()->create()])->assertForbidden();
    }

    public function test_foreign_stock_is_forbidden_when_component_is_mounted_directly(): void
    {
        $this->actingAs(User::factory()->member()->create());
        Livewire::test(StockDetail::class, ['stock' => Stock::factory()->create()])->assertForbidden();
    }

    public function test_stock_identity_is_locked_against_client_tampering(): void
    {
        $member = User::factory()->member()->create();
        $own = Stock::factory()->create(['user_id' => $member->id]);
        $foreign = Stock::factory()->create();
        $this->actingAs($member);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(StockDetail::class, ['stock' => $own])->set('stock.id', $foreign->id);
    }

    public function test_negative_adjustment_does_not_change_persisted_stock(): void
    {
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id, 'quantity' => 0]);
        $this->actingAs($member);
        Livewire::test(StockDetail::class, ['stock' => $stock])->call('adjustQuantity', -1)->assertHasErrors('quantity');
        $this->assertSame(0, $stock->fresh()->quantity);
    }

    public function test_markdown_is_rendered_with_html_and_unsafe_links_removed(): void
    {
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $member->id,
            'description' => "**Safe text**\n\n<script>alert('xss')</script>\n\n[Unsafe](javascript:alert(1))\n\n<img src=x onerror=alert(2)>",
        ]);
        $this->actingAs($member);
        Livewire::test(StockDetail::class, ['stock' => $stock])->assertSeeHtml('<strong>Safe text</strong>')
            ->assertDontSeeHtml('<script>alert(')->assertDontSeeHtml('href="javascript:')
            ->assertDontSeeHtml('<img src=x onerror=');
    }

    public function test_edit_form_exposes_all_current_stock_fields(): void
    {
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $member->id, 'sku' => 'DETAIL-ALL', 'quantity' => 8,
            'unit' => 'box', 'price' => 12000.50, 'min_stock' => 3,
        ]);
        $this->actingAs($member);
        Livewire::test(StockDetail::class, ['stock' => $stock])->call('openEditModal')
            ->assertSet('formSku', 'DETAIL-ALL')->assertSet('formQuantity', 8)
            ->assertSet('formUnit', 'box')->assertSet('formPrice', '12000.50')->assertSet('formMinStock', 3);
    }
}
