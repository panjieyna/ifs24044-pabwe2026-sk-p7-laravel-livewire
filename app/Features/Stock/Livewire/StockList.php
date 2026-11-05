<?php

namespace App\Features\Stock\Livewire;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use App\Features\Stock\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Inventaris · Ruang Stok')]
class StockList extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showFormModal = false;

    #[Locked]
    public ?int $editingStockId = null;

    public string $formName = '';

    public ?string $formSku = null;

    public ?string $formDescription = null;

    public int|string $formQuantity = 0;

    public string $formUnit = 'pcs';

    public ?string $formPrice = null;

    public int|string $formMinStock = 5;

    #[Locked]
    public ?int $confirmingStockDeletionId = null;

    public bool $showImportModal = false;

    public mixed $importFile = null;

    public bool $showAiModal = false;

    public string $aiPrompt = '';

    public int|string $aiCount = 3;

    #[Locked]
    public array $aiGeneratedItems = [];

    public array $aiSelectedIndices = [];

    public ?string $aiErrorMessage = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function boot(): void
    {
        Gate::authorize('create', Stock::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $stock = Stock::findOrFail($id);
        Gate::authorize('update', $stock);
        $this->resetErrorBag();
        $this->editingStockId = $stock->id;
        $this->formName = $stock->name;
        $this->formSku = $stock->sku;
        $this->formDescription = $stock->description;
        $this->formQuantity = $stock->quantity;
        $this->formUnit = $stock->unit;
        $this->formPrice = $stock->price;
        $this->formMinStock = $stock->min_stock;
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
    }

    public function resetForm(): void
    {
        $this->reset(['editingStockId', 'formName', 'formSku', 'formDescription', 'formQuantity', 'formUnit', 'formPrice', 'formMinStock']);
        $this->resetErrorBag();
    }

    public function saveStock(StockService $service): void
    {
        $rules = [];
        $data = [];
        foreach (['name' => 'formName', 'sku' => 'formSku', 'description' => 'formDescription', 'quantity' => 'formQuantity', 'unit' => 'formUnit', 'price' => 'formPrice', 'min_stock' => 'formMinStock'] as $field => $property) {
            $rules[$property] = StockService::rules()[$field];
            $data[$field] = $this->$property;
        }
        $this->validate($rules);
        if ($this->editingStockId) {
            $stock = Stock::findOrFail($this->editingStockId);
            Gate::authorize('update', $stock);
            $service->updateStock($stock, $data);
            session()->flash('success', 'Barang berhasil diperbarui.');
        } else {
            $service->createStock(Auth::id(), $data);
            session()->flash('success', 'Barang baru berhasil ditambahkan.');
        }
        $this->closeFormModal();
    }

    public function adjustQuantity(int $id, int $delta, StockService $service): void
    {
        $stock = Stock::findOrFail($id);
        Gate::authorize('update', $stock);
        $service->adjustQuantity($stock, $delta);
        session()->flash('success', 'Jumlah dan status stok berhasil diperbarui.');
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('delete', Stock::findOrFail($id));
        $this->confirmingStockDeletionId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingStockDeletionId = null;
    }

    public function deleteStock(StockService $service): void
    {
        if ($this->confirmingStockDeletionId === null) {
            return;
        }
        $stock = Stock::findOrFail($this->confirmingStockDeletionId);
        Gate::authorize('delete', $stock);
        $service->deleteStock($stock);
        $this->confirmingStockDeletionId = null;
        session()->flash('success', 'Barang berhasil dihapus.');
    }

    public function exportExcel(StockExcelService $service): StreamedResponse
    {
        return $service->exportToResponse(Auth::user()->stocks()->latest('id')->get(), 'stok-'.now()->format('Ymd-His').'.xlsx');
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile']);
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
    }

    public function importExcel(StockExcelService $service): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);
        $result = $service->import($this->importFile->getRealPath(), Auth::id());
        $this->closeImportModal();
        session()->flash('success', "Berhasil mengimpor {$result['imported']} barang.");
    }

    public function openAiModal(): void
    {
        $this->reset(['aiPrompt', 'aiGeneratedItems', 'aiSelectedIndices', 'aiErrorMessage', 'aiCount']);
        $this->resetErrorBag();
        $this->showAiModal = true;
    }

    public function closeAiModal(): void
    {
        $this->showAiModal = false;
    }

    public function generateWithAi(AiClientInterface $client): void
    {
        $this->validate(['aiPrompt' => ['required', 'string', 'min:3', 'max:500'], 'aiCount' => ['required', 'integer', 'min:1', 'max:10']]);
        $this->aiErrorMessage = null;
        $this->aiGeneratedItems = [];
        $this->aiSelectedIndices = [];
        try {
            $items = $client->generateStocks($this->aiPrompt, (int) $this->aiCount);
            $this->aiGeneratedItems = array_map(fn ($item) => $item->toArray(), $items);
            $this->aiSelectedIndices = array_keys($this->aiGeneratedItems);
        } catch (\Throwable) {
            $this->aiErrorMessage = 'AI belum dapat membuat barang. Periksa konfigurasi API dan coba kembali.';
        }
    }

    public function saveAiGenerated(StockService $service): void
    {
        if ($this->aiSelectedIndices === [] || $this->aiGeneratedItems === []) {
            $this->aiErrorMessage = 'Pilih minimal satu barang yang ingin disimpan.';

            return;
        }
        $this->validate(['aiSelectedIndices.*' => ['integer', 'distinct', 'in:'.implode(',', array_keys($this->aiGeneratedItems))]]);
        DB::transaction(function () use ($service): void {
            foreach ($this->aiSelectedIndices as $index) {
                $service->createStock(Auth::id(), $this->aiGeneratedItems[$index]);
            }
        });
        $count = count($this->aiSelectedIndices);
        $this->aiGeneratedItems = [];
        $this->closeAiModal();
        session()->flash('success', "Berhasil menambahkan {$count} barang dari AI.");
    }

    public function render(StockService $service): View
    {
        $summary = Auth::user()->stocks()->selectRaw('COUNT(*) as items, COALESCE(SUM(quantity), 0) as units, COALESCE(SUM(quantity * COALESCE(price, 0)), 0) as value')->first();
        $counts = Auth::user()->stocks()->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        return view('livewire.stock.stock-list', [
            'stocks' => $service->getStocksForUser(Auth::id(), $this->statusFilter ?: null, $this->search ?: null, 10),
            'statuses' => StockStatus::cases(), 'summary' => $summary, 'counts' => $counts,
        ]);
    }
}
