<?php

namespace App\Features\Ai\Services;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Services\StockService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class OpenAiStockGeneratorService implements AiClientInterface
{
    protected string $endpoint;

    protected string $apiKey;

    protected string $model;

    public function __construct(?string $endpoint = null, ?string $apiKey = null, ?string $model = null)
    {
        $this->endpoint = rtrim($endpoint ?? config('openai.endpoint'), '/');
        $this->apiKey = $apiKey ?? config('openai.key');
        $this->model = $model ?? config('openai.model');

        if (! filter_var($this->endpoint, FILTER_VALIDATE_URL) || parse_url($this->endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('OPENAI_ENDPOINT harus berupa URL HTTPS yang valid.');
        }
    }

    /** @return array<int, GeneratedStockItem> */
    public function generateStocks(string $prompt, int $count = 3): array
    {
        $prompt = trim($prompt);
        if ($prompt === '' || mb_strlen($prompt) > 500 || $count < 1 || $count > 10) {
            throw new RuntimeException('Isi prompt maksimal 500 karakter dan jumlah item antara 1 sampai 10.');
        }

        if ($this->apiKey === '' || $this->model === '') {
            throw new RuntimeException('Generator AI belum dikonfigurasi. Lengkapi OPENAI_KEY dan OPENAI_MODEL pada server.');
        }

        try {
            $response = Http::withToken($this->apiKey)->acceptJson()->timeout(30)->connectTimeout(10)
                ->post($this->endpoint.'/chat/completions', [
                    'model' => $this->model,
                    'store' => false,
                    'messages' => [
                        ['role' => 'system', 'content' => "You are an inventory planning assistant. Generate exactly {$count} realistic inventory items for the user's business. Return JSON with a stocks array. Use Indonesian names and concise Markdown descriptions. Quantity and min_stock must be nonnegative integers; prices are nonnegative IDR with at most two decimal places or null. Do not invent supplier claims or add unrelated tasks. SKU may be null. Do not include user IDs or status; stock status is calculated by the application."],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'inventory_items',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'stocks' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'additionalProperties' => false,
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'sku' => ['type' => ['string', 'null']],
                                                'description' => ['type' => ['string', 'null']],
                                                'quantity' => ['type' => 'integer'],
                                                'unit' => ['type' => 'string'],
                                                'price' => ['type' => ['number', 'null']],
                                                'min_stock' => ['type' => 'integer'],
                                            ],
                                            'required' => ['name', 'sku', 'description', 'quantity', 'unit', 'price', 'min_stock'],
                                        ],
                                    ],
                                ],
                                'required' => ['stocks'],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Layanan AI tidak dapat dihubungi. Silakan coba kembali.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Layanan AI menolak permintaan (HTTP '.$response->status().'). Periksa konfigurasi dan kuota API.');
        }

        $items = $this->parseContent((string) $response->json('choices.0.message.content'));
        if (count($items) !== $count) {
            throw new RuntimeException('Jumlah item dari AI tidak sesuai permintaan. Silakan coba kembali.');
        }

        return $items;
    }

    /** @return array<int, GeneratedStockItem> */
    public function parseContent(string $rawContent): array
    {
        $content = trim($rawContent);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content);
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI mengembalikan format respons yang tidak valid.');
        }

        $items = $decoded['stocks'] ?? (array_is_list($decoded) ? $decoded : []);
        if (! is_array($items) || $items === [] || count($items) > 10) {
            throw new RuntimeException('AI harus mengembalikan antara 1 sampai 10 item stok.');
        }

        $results = [];
        foreach ($items as $item) {
            $normalized = is_array($item) ? array_map(fn ($value) => is_string($value) ? trim($value) : $value, $item) : [];
            $validator = Validator::make($normalized, StockService::rules());
            if ($validator->fails()) {
                throw new RuntimeException('Data item dari AI tidak memenuhi aturan inventaris. Silakan coba kembali.');
            }

            $data = $validator->validated();
            $results[] = new GeneratedStockItem(
                name: trim($data['name']),
                sku: $data['sku'] ?? null,
                description: $data['description'] ?? null,
                quantity: (int) $data['quantity'],
                unit: trim($data['unit']),
                price: isset($data['price']) ? (float) $data['price'] : null,
                minStock: (int) $data['min_stock'],
            );
        }

        return $results;
    }
}
