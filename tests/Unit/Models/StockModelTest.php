<?php

namespace Tests\Unit\Models;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $stock->user);
        $this->assertEquals($user->id, $stock->user->id);
    }

    public function test_stock_casts_quantity_minimum_and_price(): void
    {
        $stock = Stock::factory()->create(['quantity' => '20', 'min_stock' => '5', 'price' => '12345.5']);
        $this->assertSame(20, $stock->fresh()->quantity);
        $this->assertSame(5, $stock->fresh()->min_stock);
        $this->assertSame('12345.50', $stock->fresh()->price);
    }

    public function test_stock_factory_states(): void
    {
        $availableStock = Stock::factory()->available()->create();
        $this->assertEquals('available', $availableStock->status);

        $lowStock = Stock::factory()->lowStock()->create();
        $this->assertEquals('low_stock', $lowStock->status);

        $outOfStock = Stock::factory()->outOfStock()->create();
        $this->assertEquals('out_of_stock', $outOfStock->status);
    }

    public function test_cover_image_url_returns_correct_url_or_null(): void
    {
        $stockWithoutCover = Stock::factory()->create(['cover_image_path' => null]);
        $this->assertNull($stockWithoutCover->coverImageUrl());

        Storage::fake('public');
        Storage::disk('public')->put('stock-covers/sample.png', 'content');

        $stockWithCover = Stock::factory()->create(['cover_image_path' => 'stock-covers/sample.png']);
        $this->assertNotNull($stockWithCover->coverImageUrl());
        $this->assertStringContainsString('stock-covers/sample.png', $stockWithCover->coverImageUrl());

        $stockWithCover->update(['cover_image_path' => 'stock-covers/missing.png']);
        $this->assertNull($stockWithCover->coverImageUrl());
    }

    public function test_status_cannot_be_forged_and_recalculates_when_minimum_changes(): void
    {
        $stock = Stock::factory()->create(['quantity' => 10, 'min_stock' => 5, 'status' => 'out_of_stock']);
        $this->assertSame('available', $stock->status);

        $stock->update(['min_stock' => 10]);
        $this->assertSame('low_stock', $stock->fresh()->status);

        $stock->update(['quantity' => 0, 'status' => 'available']);
        $this->assertSame('out_of_stock', $stock->fresh()->status);
    }

    public function test_deleting_user_cascades_owned_inventory(): void
    {
        $stock = Stock::factory()->create();
        $stock->user->delete();

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }
}
