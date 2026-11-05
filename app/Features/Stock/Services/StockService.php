<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Models\Stock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(protected StockRepositoryInterface $stockRepository) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:50000'],
            'quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function getStocksForUser(int $userId, ?string $status = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->stockRepository->forUser($userId, $status, $search, $perPage);
    }

    /** @param array<string, mixed> $data */
    public function createStock(int $userId, array $data): Stock
    {
        $attributes = $this->validatedData(array_replace([
            'sku' => null, 'description' => null, 'quantity' => 0,
            'unit' => 'pcs', 'price' => null, 'min_stock' => 5,
        ], $data));

        return $this->stockRepository->create(['user_id' => $userId, ...$attributes]);
    }

    /** @param array<string, mixed> $data */
    public function updateStock(Stock $stock, array $data): bool
    {
        $attributes = $this->validatedData(array_replace(
            $stock->only(array_keys(self::rules())),
            $data,
        ));

        return $this->stockRepository->update($stock, $attributes);
    }

    /** Serialize concurrent adjustments using the latest persisted quantity. */
    public function adjustQuantity(Stock $stock, int $delta): bool
    {
        return DB::transaction(function () use ($stock, $delta): bool {
            $lockedStock = Stock::query()->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();
            $newQuantity = $lockedStock->quantity + $delta;

            if ($newQuantity < 0 || $newQuantity > 2147483647) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah stok harus berada antara 0 dan 2.147.483.647.',
                ]);
            }

            $updated = $this->stockRepository->update($lockedStock, ['quantity' => $newQuantity]);
            $stock->refresh();

            return $updated;
        });
    }

    public function updateCoverPhoto(Stock $stock, UploadedFile $file): string
    {
        Validator::make(['cover' => $file], [
            'cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ])->validate();

        $path = $file->store('stock-covers', 'public');
        $oldPath = $stock->cover_image_path;
        $this->stockRepository->update($stock, ['cover_image_path' => $path]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    public function deleteCoverPhoto(Stock $stock): bool
    {
        if ($stock->cover_image_path) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        return $this->stockRepository->update($stock, ['cover_image_path' => null]);
    }

    public function deleteStock(Stock $stock): bool
    {
        if ($stock->cover_image_path) {
            Storage::disk('public')->delete($stock->cover_image_path);
        }

        return $this->stockRepository->delete($stock);
    }

    /**
     * Validate editable domain fields; ownership, status and file paths are server controlled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedData(array $data): array
    {
        return Validator::make(Arr::only($data, array_keys(self::rules())), self::rules())->validate();
    }
}
