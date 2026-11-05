<?php

namespace App\Features\Stock\Livewire;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class StockDetail extends Component
{
    use WithFileUploads;

    #[Locked]
    public Stock $stock;

    public string $formName = '';

    public ?string $formSku = null;

    public ?string $formDescription = null;

    public int|string $formQuantity = 0;

    public string $formUnit = 'pcs';

    public ?string $formPrice = null;

    public int|string $formMinStock = 5;

    public mixed $coverPhoto = null;

    public bool $showEditModal = false;

    public bool $confirmingStockDeletion = false;

    public bool $confirmingCoverDeletion = false;

    public function boot(): void
    {
        Gate::authorize('create', Stock::class);
    }

    public function mount(Stock $stock): void
    {
        Gate::authorize('view', $stock);
        $this->stock = $stock;
    }

    public function openEditModal(): void
    {
        Gate::authorize('update', $this->stock);
        $this->resetErrorBag();
        $this->formName = $this->stock->name;
        $this->formSku = $this->stock->sku;
        $this->formDescription = $this->stock->description;
        $this->formQuantity = $this->stock->quantity;
        $this->formUnit = $this->stock->unit;
        $this->formPrice = $this->stock->price;
        $this->formMinStock = $this->stock->min_stock;
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
    }

    public function saveStock(StockService $service): void
    {
        Gate::authorize('update', $this->stock);
        $rules = [];
        $data = [];
        foreach (['name' => 'formName', 'sku' => 'formSku', 'description' => 'formDescription', 'quantity' => 'formQuantity', 'unit' => 'formUnit', 'price' => 'formPrice', 'min_stock' => 'formMinStock'] as $field => $property) {
            $rules[$property] = StockService::rules()[$field];
            $data[$field] = $this->$property;
        }
        $this->validate($rules);
        $service->updateStock($this->stock, $data);
        $this->stock->refresh();
        $this->closeEditModal();
        session()->flash('success', 'Informasi barang berhasil disimpan.');
    }

    public function adjustQuantity(int $delta, StockService $service): void
    {
        Gate::authorize('update', $this->stock);
        $service->adjustQuantity($this->stock, $delta);
        $this->stock->refresh();
        session()->flash('success', 'Jumlah dan status stok berhasil diperbarui.');
    }

    public function updatedCoverPhoto(StockService $service): void
    {
        Gate::authorize('update', $this->stock);
        $this->validate(['coverPhoto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);
        $service->updateCoverPhoto($this->stock, $this->coverPhoto);
        $this->stock->refresh();
        $this->reset('coverPhoto');
        session()->flash('success', 'Gambar barang berhasil diperbarui.');
    }

    public function confirmDeleteCover(): void
    {
        Gate::authorize('update', $this->stock);
        $this->confirmingCoverDeletion = true;
    }

    public function cancelDeleteCover(): void
    {
        $this->confirmingCoverDeletion = false;
    }

    public function deleteCoverPhoto(StockService $service): void
    {
        Gate::authorize('update', $this->stock);
        $service->deleteCoverPhoto($this->stock);
        $this->stock->refresh();
        $this->confirmingCoverDeletion = false;
        session()->flash('success', 'Gambar barang berhasil dihapus.');
    }

    public function confirmDeleteStock(): void
    {
        Gate::authorize('delete', $this->stock);
        $this->confirmingStockDeletion = true;
    }

    public function cancelDeleteStock(): void
    {
        $this->confirmingStockDeletion = false;
    }

    public function deleteStock(StockService $service): mixed
    {
        Gate::authorize('delete', $this->stock);
        $service->deleteStock($this->stock);
        session()->flash('success', 'Barang berhasil dihapus.');

        return $this->redirectRoute('stocks.index');
    }

    public function render(): View
    {
        return view('livewire.stock.stock-detail', ['status' => StockStatus::from($this->stock->status)])->title($this->stock->name.' · Ruang Stok');
    }
}
