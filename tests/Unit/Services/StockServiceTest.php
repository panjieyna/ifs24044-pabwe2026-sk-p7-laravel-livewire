<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;

    protected StockRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StockRepository;
        $this->service = new StockService($this->repository);
    }

    public function test_get_stocks_for_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);

        $paginator = $this->service->getStocksForUser($user->id);
        $this->assertCount(2, $paginator);
    }

    public function test_create_stock(): void
    {
        $user = User::factory()->create();
        $stock = $this->service->createStock($user->id, [
            'name' => 'Service Stock',
            'description' => 'Desc',
            'quantity' => 15,
            'unit' => 'pcs',
            'price' => '150000.50',
            'min_stock' => 5,
        ]);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals('Service Stock', $stock->name);
        $this->assertEquals('available', $stock->status);
        $this->assertSame('150000.50', $stock->price);
    }

    public function test_update_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Old Title']);

        $updated = $this->service->updateStock($stock, [
            'name' => 'New Title',
            'description' => 'Updated Desc',
        ]);

        $this->assertTrue($updated);
        $stock->refresh();
        $this->assertEquals('New Title', $stock->name);
        $this->assertEquals('Updated Desc', $stock->description);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $deleted = $this->service->deleteStock($stock);
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_quantity_adjustment_automatically_synchronizes_status(): void
    {
        $stock = Stock::factory()->create(['quantity' => 4, 'min_stock' => 5]);
        $this->assertSame('low_stock', $stock->status);

        $this->assertTrue($this->service->adjustQuantity($stock, 2));
        $this->assertSame(6, $stock->quantity);
        $this->assertSame('available', $stock->status);

        $this->service->adjustQuantity($stock, -1);
        $this->assertSame('low_stock', $stock->status);

        $this->service->adjustQuantity($stock, -5);
        $this->assertSame(0, $stock->quantity);
        $this->assertSame('out_of_stock', $stock->status);
    }

    public function test_adjustment_uses_latest_persisted_quantity_instead_of_stale_model(): void
    {
        $stock = Stock::factory()->create(['quantity' => 10]);
        $staleStock = Stock::findOrFail($stock->id);
        $this->service->adjustQuantity($stock, 3);
        $this->service->adjustQuantity($staleStock, 2);

        $this->assertSame(15, $stock->fresh()->quantity);
        $this->assertSame(15, $staleStock->quantity);
    }

    public function test_negative_adjustment_is_rejected_without_changing_stock(): void
    {
        $stock = Stock::factory()->create(['quantity' => 2]);

        try {
            $this->service->adjustQuantity($stock, -3);
            $this->fail('Negative resulting stock must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
            $this->assertSame(2, $stock->fresh()->quantity);
        }
    }

    public function test_overflow_adjustment_is_rejected_without_changing_stock(): void
    {
        $stock = Stock::factory()->create(['quantity' => 2147483647]);
        $this->expectException(ValidationException::class);

        try {
            $this->service->adjustQuantity($stock, 1);
        } finally {
            $this->assertSame(2147483647, $stock->fresh()->quantity);
        }
    }

    public function test_defaults_and_ownership_are_server_controlled(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $stock = $this->service->createStock($owner->id, [
            'name' => 'Default Stock', 'user_id' => $other->id,
            'status' => 'available', 'cover_image_path' => '/secret/file.png',
        ]);

        $this->assertSame($owner->id, $stock->user_id);
        $this->assertSame(0, $stock->quantity);
        $this->assertSame(5, $stock->min_stock);
        $this->assertSame('pcs', $stock->unit);
        $this->assertNull($stock->price);
        $this->assertNull($stock->cover_image_path);
        $this->assertSame('out_of_stock', $stock->status);

        $this->service->updateStock($stock, [
            'user_id' => $other->id, 'quantity' => 8,
            'status' => 'out_of_stock', 'cover_image_path' => 'arbitrary.png',
        ]);
        $this->assertSame($owner->id, $stock->fresh()->user_id);
        $this->assertNull($stock->fresh()->cover_image_path);
        $this->assertSame('available', $stock->fresh()->status);
    }

    public function test_validation_rejects_invalid_domain_fields(): void
    {
        $owner = User::factory()->create();
        $invalidValues = [
            'name' => '', 'sku' => str_repeat('s', 101),
            'description' => str_repeat('d', 50001), 'quantity' => -1,
            'unit' => '', 'price' => '-1.123', 'min_stock' => -1,
        ];

        try {
            $this->service->createStock($owner->id, $invalidValues);
            $this->fail('Invalid input must fail validation.');
        } catch (ValidationException $exception) {
            foreach (array_keys($invalidValues) as $field) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
            $this->assertDatabaseCount('stocks', 0);
        }
    }

    public function test_nullable_description_sku_and_price_can_be_cleared(): void
    {
        $stock = Stock::factory()->create();
        $this->service->updateStock($stock, ['description' => null, 'sku' => null, 'price' => null]);

        $this->assertNull($stock->fresh()->description);
        $this->assertNull($stock->fresh()->sku);
        $this->assertNull($stock->fresh()->price);
    }

    public function test_cover_upload_rejects_non_image_files(): void
    {
        $stock = Stock::factory()->create();
        $this->expectException(ValidationException::class);
        $this->service->updateCoverPhoto($stock, UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'));
    }

    public function test_deleting_an_absent_cover_is_safe(): void
    {
        Storage::fake('public');
        $stock = Stock::factory()->create(['cover_image_path' => null]);
        $this->assertTrue($this->service->deleteCoverPhoto($stock));
        $this->assertNull($stock->fresh()->cover_image_path);
    }

    public function test_cover_photo_management(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $fakeFile = UploadedFile::fake()->image('cover.jpg');

        // Update cover
        $path = $this->service->updateCoverPhoto($stock, $fakeFile);
        $stock->refresh();
        $this->assertEquals($path, $stock->cover_image_path);
        Storage::disk('public')->assertExists($path);

        // Update cover again (replaces old)
        $newFakeFile = UploadedFile::fake()->image('new-cover.jpg');
        $newPath = $this->service->updateCoverPhoto($stock, $newFakeFile);
        $stock->refresh();
        $this->assertEquals($newPath, $stock->cover_image_path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($newPath);

        // Delete cover
        $deleted = $this->service->deleteCoverPhoto($stock);
        $this->assertTrue($deleted);
        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
        Storage::disk('public')->assertMissing($newPath);

        // Delete stock cleans up cover photo if present
        $anotherFile = UploadedFile::fake()->image('another.jpg');
        $anotherPath = $this->service->updateCoverPhoto($stock, $anotherFile);
        Storage::disk('public')->assertExists($anotherPath);

        $this->service->deleteStock($stock);
        Storage::disk('public')->assertMissing($anotherPath);
    }
}
