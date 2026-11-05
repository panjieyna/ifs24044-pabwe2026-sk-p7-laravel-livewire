<?php

namespace Tests\Unit\Services;

use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    protected function item(array $overrides = []): array
    {
        return array_replace(['name' => 'Kopi Arabika', 'sku' => 'KOP-001', 'description' => '**Segar**', 'quantity' => 12, 'unit' => 'pack', 'price' => 45000.50, 'min_stock' => 5], $overrides);
    }

    protected function service(): OpenAiStockGeneratorService
    {
        return new OpenAiStockGeneratorService('https://api.openai.com/v1/', 'test-key', 'gpt-4o-mini');
    }

    public function test_successful_generation_uses_strict_stock_schema_and_preserves_prompt(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['stocks' => [$this->item(), $this->item(['name' => 'Gula Aren', 'sku' => null, 'description' => null, 'price' => null])]])]]],
        ])]);
        $results = $this->service()->generateStocks('Buatkan stok kedai kopi', 2);

        $this->assertCount(2, $results);
        $this->assertSame('Kopi Arabika', $results[0]->name);
        $this->assertSame('available', $results[0]->toArray()['status']);
        $this->assertNull($results[1]->description);
        $this->assertNull($results[1]->price);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['messages'][1]['content'] === 'Buatkan stok kedai kopi'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['store'] === false);
    }

    #[DataProvider('invalidPromptProvider')]
    public function test_invalid_prompt_or_count_is_rejected_before_network_call(string $prompt, int $count): void
    {
        Http::fake();
        try {
            $this->service()->generateStocks($prompt, $count);
            $this->fail('Expected input rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('500 karakter', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    public static function invalidPromptProvider(): array
    {
        return [['   ', 3], [str_repeat('a', 501), 3], ['Kopi', 0], ['Kopi', 11]];
    }

    public function test_missing_credentials_are_reported_without_network_call(): void
    {
        Http::fake();
        config(['openai.endpoint' => 'https://api.openai.com/v1', 'openai.key' => '', 'openai.model' => 'gpt-4o-mini']);
        try {
            (new OpenAiStockGeneratorService)->generateStocks('Kopi', 1);
            $this->fail('Expected configuration rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('belum dikonfigurasi', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidEndpointProvider')]
    public function test_invalid_endpoint_is_rejected(string $endpoint): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('URL HTTPS');
        new OpenAiStockGeneratorService($endpoint, 'test-key', 'model');
    }

    public static function invalidEndpointProvider(): array
    {
        return [['not-a-url'], ['http://api.openai.com/v1']];
    }

    public function test_api_failure_does_not_expose_provider_error_or_credentials(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Secret test-key exposed upstream']], 401)]);
        try {
            $this->service()->generateStocks('Kopi', 1);
            $this->fail('Expected upstream failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 401', $exception->getMessage());
            $this->assertStringNotContainsString('test-key', $exception->getMessage());
        }
    }

    public function test_connection_failure_has_safe_actionable_message(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak dapat dihubungi');
        $this->service()->generateStocks('Kopi', 1);
    }

    public function test_count_mismatch_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode(['stocks' => [$this->item()]])]]]])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jumlah item');
        $this->service()->generateStocks('Kopi', 2);
    }

    public function test_parse_content_accepts_markdown_fences_and_bare_arrays(): void
    {
        $results = $this->service()->parseContent("```json\n".json_encode([$this->item()])."\n```");
        $this->assertCount(1, $results);
        $this->assertSame('Kopi Arabika', $results[0]->name);
    }

    #[DataProvider('invalidJsonProvider')]
    public function test_parse_content_invalid_json(string $content): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('format respons');
        $this->service()->parseContent($content);
    }

    public static function invalidJsonProvider(): array
    {
        return [['not-json'], ['null'], ['"text"']];
    }

    #[DataProvider('emptyItemsProvider')]
    public function test_parse_content_requires_bounded_nonempty_item_array(string $content): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1 sampai 10');
        $this->service()->parseContent($content);
    }

    public static function emptyItemsProvider(): array
    {
        return [['{"stocks":[]}'], ['{"stocks":"invalid"}'], ['{}'], [json_encode(['stocks' => array_fill(0, 11, [])])]];
    }

    #[DataProvider('invalidItemsProvider')]
    public function test_parse_content_invalid_items_are_rejected(array $item): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aturan inventaris');
        $this->service()->parseContent(json_encode(['stocks' => [$item]]));
    }

    public static function invalidItemsProvider(): array
    {
        return [[['bad_key' => 'value']], [['name' => ['malicious']]], [['name' => 'Kopi', 'quantity' => -1, 'unit' => 'pcs', 'min_stock' => 5]]];
    }

    public function test_non_object_item_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aturan inventaris');
        $this->service()->parseContent('{"stocks":["bad item"]}');
    }
}
