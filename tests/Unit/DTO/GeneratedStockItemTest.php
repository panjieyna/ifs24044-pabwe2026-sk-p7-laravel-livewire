<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array_match_inventory_schema(): void
    {
        $dto = new GeneratedStockItem(name: 'Kopi Arabika', sku: 'KOP-001', description: '**Segar**', quantity: 12, unit: 'pack', price: 45000.50, minStock: 5);

        $this->assertSame('Kopi Arabika', $dto->name);
        $this->assertSame([
            'name' => 'Kopi Arabika', 'sku' => 'KOP-001', 'description' => '**Segar**',
            'quantity' => 12, 'unit' => 'pack', 'price' => 45000.50, 'min_stock' => 5, 'status' => 'available',
        ], $dto->toArray());
    }

    public function test_defaults_and_stock_status_are_derived(): void
    {
        $dto = new GeneratedStockItem('Kopi');
        $this->assertSame('out_of_stock', $dto->toArray()['status']);
        $this->assertNull($dto->sku);
        $this->assertNull($dto->price);
        $this->assertSame('pcs', $dto->unit);
        $this->assertSame('low_stock', (new GeneratedStockItem('Kopi', quantity: 5))->toArray()['status']);
    }

    public function test_invalid_generated_data_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        new GeneratedStockItem('Kopi', quantity: -1);
    }
}
