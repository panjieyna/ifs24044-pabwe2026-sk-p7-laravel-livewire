<?php

namespace App\Features\Ai\DTO;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Services\StockService;
use Illuminate\Support\Facades\Validator;

readonly class GeneratedStockItem
{
    public function __construct(
        public string $name,
        public ?string $sku = null,
        public ?string $description = null,
        public int $quantity = 0,
        public string $unit = 'pcs',
        public ?float $price = null,
        public int $minStock = 5,
    ) {
        Validator::make([
            'name' => $name,
            'sku' => $sku,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'price' => $price,
            'min_stock' => $minStock,
        ], StockService::rules())->validate();
    }

    /** @return array<string, string|int|float|null> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'price' => $this->price,
            'min_stock' => $this->minStock,
            'status' => StockStatus::fromQuantity($this->quantity, $this->minStock)->value,
        ];
    }
}
