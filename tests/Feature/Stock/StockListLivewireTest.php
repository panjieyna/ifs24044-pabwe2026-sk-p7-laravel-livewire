<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockListLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stocks(): void
    {
        $this->get('/stocks')->assertRedirect(route('login'));
    }

    public function test_member_can_view_own_stocks(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Tugas Spesifik Member']);

        $otherUser = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $otherUser->id, 'name' => 'Tugas Rahasia Orang Lain']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->assertSee('Tugas Spesifik Member')
            ->assertDontSee('Tugas Rahasia Orang Lain');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Validation error
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', '')
            ->set('formQuantity', -1)
            ->set('formMinStock', -1)
            ->set('formUnit', '')
            ->set('formPrice', '-1.234')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formQuantity', 'formMinStock', 'formUnit', 'formPrice']);

        // Success
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Stock Baru')
            ->set('formDescription', 'Deskripsi stock')
            ->set('formQuantity', 15)
            ->set('formUnit', 'pcs')
            ->set('formPrice', '12500.50')
            ->set('formMinStock', 5)
            ->set('formSku', 'LIVE-001')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Stock Baru',
            'description' => 'Deskripsi stock',
        ]);
    }

    public function test_edit_stock_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Judul Lama']);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Judul Lama')
            ->set('formName', 'Judul Telah Diedit')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Judul Telah Diedit',
        ]);
    }

    public function test_adjust_quantity_synchronizes_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 5]);

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, 1);

        $this->assertEquals('available', $stock->fresh()->status);
        $this->assertSame(6, $stock->fresh()->quantity);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock');

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingStockDeletionId', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Makan Siang', 'quantity' => 20]);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Makan Malam', 'quantity' => 5]);

        Livewire::test(StockList::class)
            ->set('search', 'Siang')
            ->assertSee('Makan Siang')
            ->assertDontSee('Makan Malam')
            ->set('search', '')
            ->set('statusFilter', 'low_stock')
            ->assertSee('Makan Malam')
            ->assertDontSee('Makan Siang');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Exportable Stock']);

        Livewire::test(StockList::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_import_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(StockExcelService::HEADERS, null, 'A1');
        $sheet->fromArray(['Imported Excel Stock', 'IMP-001', 'Excel Desc', 12, 'pcs', 1000, 5, 'available', null], null, 'A2');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $fileContent = file_get_contents($tempPath);
        @unlink($tempPath);

        $uploadedFile = UploadedFile::fake()->createWithContent('stocks.xlsx', $fileContent);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->set('importFile', $uploadedFile)
            ->call('importExcel')
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'description' => 'Excel Desc',
        ]);
    }

    public function test_ai_generation_and_saving(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Mock AiClientInterface
        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willReturn([
            new GeneratedStockItem(name: 'AI Item 1', description: 'Desc 1', quantity: 8),
            new GeneratedStockItem(name: 'AI Item 2', description: 'Desc 2', quantity: 2),
        ]);
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        // Test validation for prompt and count
        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Buatkan saya rencana')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2);

        // Try saving with no items selected
        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu barang yang ingin disimpan.');

        // Save selected items
        $component->set('aiSelectedIndices', [0, 1])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Item 1']);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'AI Item 2']);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $mockAiClient = $this->createStub(AiClientInterface::class);
        $mockAiClient->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $mockAiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Error test')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'AI belum dapat membuat barang. Periksa konfigurasi API dan coba kembali.')
            ->assertDontSee('API quota exceeded');
    }

    public function test_close_modals(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->call('closeFormModal')
            ->assertSet('showFormModal', false)
            ->call('openImportModal')
            ->call('closeImportModal')
            ->assertSet('showImportModal', false)
            ->call('openAiModal')
            ->call('closeAiModal')
            ->assertSet('showAiModal', false);
    }

    public function test_admin_cannot_open_member_inventory_component(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(StockList::class)->assertForbidden();
    }

    public function test_foreign_stock_actions_are_forbidden(): void
    {
        $member = User::factory()->member()->create();
        $foreign = Stock::factory()->create(['quantity' => 8]);
        $this->actingAs($member);

        Livewire::test(StockList::class)->call('openEditModal', $foreign->id)->assertForbidden();
        Livewire::test(StockList::class)->call('adjustQuantity', $foreign->id, -1)->assertForbidden();
        Livewire::test(StockList::class)->call('confirmDelete', $foreign->id)->assertForbidden();
        $this->assertDatabaseHas('stocks', ['id' => $foreign->id, 'quantity' => 8]);
    }

    public function test_delete_without_confirmation_is_a_safe_no_op(): void
    {
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id]);
        $this->actingAs($member);

        Livewire::test(StockList::class)->call('deleteStock')->assertHasNoErrors();
        $this->assertDatabaseHas('stocks', ['id' => $stock->id]);
    }

    public function test_negative_adjustment_surfaces_validation_and_preserves_quantity(): void
    {
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id, 'quantity' => 0]);
        $this->actingAs($member);

        Livewire::test(StockList::class)->call('adjustQuantity', $stock->id, -1)->assertHasErrors(['quantity']);
        $this->assertSame(0, $stock->fresh()->quantity);
        $this->assertSame('out_of_stock', $stock->fresh()->status);
    }

    public function test_opening_create_modal_clears_edit_state_and_errors(): void
    {
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id, 'name' => 'Existing Stock']);
        $this->actingAs($member);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->set('formName', '')
            ->call('saveStock')->assertHasErrors('formName')
            ->call('openCreateModal')
            ->assertSet('editingStockId', null)
            ->assertSet('formName', '')
            ->assertSet('formQuantity', 0)
            ->assertSet('formUnit', 'pcs')
            ->assertSet('formMinStock', 5)
            ->assertHasNoErrors();
    }

    public function test_import_requires_a_valid_file_and_does_not_write_on_invalid_rows(): void
    {
        $member = User::factory()->member()->create();
        $this->actingAs($member);
        Livewire::test(StockList::class)->call('openImportModal')->call('importExcel')->assertHasErrors('importFile');

        $file = UploadedFile::fake()->createWithContent('invalid.csv', "name,sku,description,quantity,unit,price,min_stock\nValid Item,A,Description,10,pcs,1000,5\nInvalid Item,B,Description,-1,pcs,1000,5\n");
        Livewire::test(StockList::class)->call('openImportModal')->set('importFile', $file)->call('importExcel')->assertHasErrors('importFile');
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_ai_cannot_save_before_generation(): void
    {
        $this->actingAs(User::factory()->member()->create());
        Livewire::test(StockList::class)->call('openAiModal')->set('aiSelectedIndices', [0])->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu barang yang ingin disimpan.');
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_ai_selection_rejects_duplicate_invalid_and_out_of_range_indices(): void
    {
        $this->actingAs(User::factory()->member()->create());
        $client = $this->createStub(AiClientInterface::class);
        $client->method('generateStocks')->willReturn([new GeneratedStockItem(name: 'Generated Item', quantity: 7)]);
        $this->app->instance(AiClientInterface::class, $client);

        foreach ([[0, 0], [99], ['invalid']] as $indices) {
            Livewire::test(StockList::class)->call('openAiModal')->set('aiPrompt', 'Peralatan kantor')
                ->call('generateWithAi')->set('aiSelectedIndices', $indices)->call('saveAiGenerated')
                ->assertHasErrors('aiSelectedIndices.0');
        }
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_ai_count_is_bounded_and_only_selected_items_are_saved(): void
    {
        $member = User::factory()->member()->create();
        $this->actingAs($member);
        $client = $this->createStub(AiClientInterface::class);
        $client->method('generateStocks')->willReturn([
            new GeneratedStockItem(name: 'Selected Item', quantity: 8),
            new GeneratedStockItem(name: 'Unselected Item', quantity: 2),
        ]);
        $this->app->instance(AiClientInterface::class, $client);

        Livewire::test(StockList::class)->set('aiPrompt', 'Peralatan kantor')->set('aiCount', 11)
            ->call('generateWithAi')->assertHasErrors('aiCount');
        Livewire::test(StockList::class)->call('openAiModal')->set('aiPrompt', 'Peralatan kantor')->set('aiCount', 2)
            ->call('generateWithAi')->set('aiSelectedIndices', ['0'])->call('saveAiGenerated')
            ->assertHasNoErrors()->assertSet('aiGeneratedItems', [])->assertSet('showAiModal', false);
        $this->assertDatabaseHas('stocks', ['user_id' => $member->id, 'name' => 'Selected Item', 'status' => 'available']);
        $this->assertDatabaseMissing('stocks', ['name' => 'Unselected Item']);
    }

    public function test_ai_generated_payload_is_locked_against_client_tampering(): void
    {
        $this->actingAs(User::factory()->member()->create());
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(StockList::class)->set('aiGeneratedItems', [['name' => 'Forged Item', 'quantity' => 999]]);
    }

    public function test_editing_stock_identity_is_locked_against_client_tampering(): void
    {
        $this->actingAs(User::factory()->member()->create());
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(StockList::class)->set('editingStockId', Stock::factory()->create()->id);
    }

    public function test_deletion_identity_is_locked_against_client_tampering(): void
    {
        $this->actingAs(User::factory()->member()->create());
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(StockList::class)->set('confirmingStockDeletionId', Stock::factory()->create()->id);
    }

    public function test_search_and_filter_reset_pagination(): void
    {
        $member = User::factory()->member()->create();
        Stock::factory()->count(12)->create(['user_id' => $member->id]);
        $this->actingAs($member);

        Livewire::test(StockList::class)->call('setPage', 2)->assertSet('paginators.page', 2)
            ->set('search', 'SKU')->assertSet('paginators.page', 1)
            ->call('setPage', 2)->set('statusFilter', 'available')->assertSet('paginators.page', 1);
    }
}
