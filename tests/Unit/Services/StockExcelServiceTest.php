<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Services\StockExcelService;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockExcelService $service;

    protected array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockExcelService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    protected function workbook(array $rows, array $headers = StockExcelService::HEADERS, bool $legacy = false): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headers, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'stock_excel_');
        $this->files[] = $path;
        $writer = $legacy ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function test_build_spreadsheet_and_export_response_use_inventory_headers_and_formatting(): void
    {
        $user = User::factory()->create();
        $stocks = Stock::factory()->count(2)->create(['user_id' => $user->id, 'name' => 'Kopi Arabika', 'quantity' => 12, 'unit' => 'pack', 'price' => 45000.50]);
        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Custom Stocks');
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Custom Stocks', $sheet->getTitle());
        $this->assertSame(StockExcelService::HEADERS, $sheet->rangeToArray('A1:I1')[0]);
        $this->assertSame('Kopi Arabika', $sheet->getCell('A2')->getValue());
        $this->assertSame(12, $sheet->getCell('D2')->getValue());
        $this->assertSame(45000.50, (float) $sheet->getCell('F2')->getValue());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:I3', $sheet->getAutoFilter()->getRange());
        $this->assertStringContainsString('Rp', $sheet->getStyle('F2')->getNumberFormat()->getFormatCode());

        $response = $this->service->exportToResponse($stocks, 'inventaris.xlsx');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inventaris.xlsx', $response->headers->get('Content-Disposition'));
        ob_start();
        $response->sendContent();
        $binary = ob_get_clean();
        $this->assertStringStartsWith('PK', $binary);
        $path = tempnam(sys_get_temp_dir(), 'export_excel_');
        $this->files[] = $path;
        file_put_contents($path, $binary);
        $loaded = IOFactory::load($path);
        $this->assertSame('Kopi Arabika', $loaded->getActiveSheet()->getCell('A2')->getValue());
        $loaded->disconnectWorksheets();
    }

    public function test_empty_export_and_nullable_fields_are_supported(): void
    {
        $empty = $this->service->buildSpreadsheet(new Collection);
        $this->assertSame('A1:I2', $empty->getActiveSheet()->getAutoFilter()->getRange());
        $stock = new Stock(['name' => 'Kopi', 'quantity' => 0, 'unit' => 'pcs', 'min_stock' => 5, 'status' => 'out_of_stock']);
        $sheet = $this->service->buildSpreadsheet(new Collection([$stock]))->getActiveSheet();
        $this->assertSame('', $sheet->getCell('B2')->getValue());
        $this->assertSame('', $sheet->getCell('I2')->getValue());
    }

    public function test_export_forces_untrusted_text_to_string_and_round_trip_keeps_it_safe(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => '=HYPERLINK("https://example.com")', 'sku' => '+SUM(1,1)', 'description' => '@malicious', 'quantity' => 0]);
        $spreadsheet = $this->service->buildSpreadsheet(new Collection([$stock]));
        $sheet = $spreadsheet->getActiveSheet();
        foreach (['A2', 'B2', 'C2'] as $address) {
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($address)->getDataType());
        }
        $path = tempnam(sys_get_temp_dir(), 'safe_excel_');
        $this->files[] = $path;
        (new Xlsx($spreadsheet))->save($path);
        $other = User::factory()->create();
        $this->assertSame(['imported' => 1], $this->service->import($path, $other->id));
        $this->assertDatabaseHas('stocks', ['user_id' => $other->id, 'name' => $stock->name, 'status' => 'out_of_stock']);
    }

    public function test_import_spreadsheet_validates_headers_and_recalculates_status_for_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $path = $this->workbook([
            ['Kopi Arabika', 'KOP-001', '**Segar**', 12, 'pack', 45000.50, 5, 'out_of_stock', '2020-01-01', $other->id],
            ['', '', '', '', '', '', '', '', ''],
            ['Gula Aren', '   ', '   ', 2, 'kg', '   ', 5, 'available', ''],
            ['Susu', null, null, 0, 'box', 10000, 0, 'available', ''],
        ], [...StockExcelService::HEADERS, 'user_id']);

        $this->assertSame(['imported' => 3], $this->service->import($path, $user->id));
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Kopi Arabika', 'status' => 'available', 'quantity' => 12, 'price' => 45000.50]);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Gula Aren', 'status' => 'low_stock', 'description' => null, 'sku' => null, 'price' => null]);
        $this->assertDatabaseHas('stocks', ['user_id' => $user->id, 'name' => 'Susu', 'status' => 'out_of_stock']);
        $this->assertSame(0, $other->stocks()->count());
    }

    public function test_import_supports_xls_and_csv_and_reordered_case_insensitive_headers(): void
    {
        $user = User::factory()->create();
        $path = $this->workbook([['Kopi', null, null, 10, 'pcs', null, 5]], StockExcelService::HEADERS, true);
        $this->assertSame(1, $this->service->import($path, $user->id)['imported']);
        $csvPath = tempnam(sys_get_temp_dir(), 'stock_csv_');
        $this->files[] = $csvPath;
        file_put_contents($csvPath, "QUANTITY,Name,unit,min_stock,description,sku,price\n4,Gula,kg,5,,GUL-01,12000\n");
        $this->assertSame(1, $this->service->import($csvPath, $user->id)['imported']);
        $this->assertDatabaseHas('stocks', ['name' => 'Gula', 'quantity' => 4, 'status' => 'low_stock']);
    }

    public function test_import_empty_template_is_valid(): void
    {
        $user = User::factory()->create();
        $this->assertSame(['imported' => 0], $this->service->import($this->workbook([]), $user->id));
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_import_invalid_later_row_writes_nothing(): void
    {
        $user = User::factory()->create();
        $path = $this->workbook([
            ['Kopi', null, null, 12, 'pcs', null, 5],
            ['Gula', null, null, -1, 'pcs', null, 5],
        ]);
        try {
            $this->service->import($path, $user->id);
            $this->fail('Expected rejected row.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Baris 3:', $exception->errors()['importFile'][0]);
            $this->assertDatabaseCount('stocks', 0);
        }
    }

    public function test_import_missing_headers_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Header wajib');
        $this->service->import($this->workbook([['Old todo']], ['title']), $user->id);
    }

    public function test_import_formula_cells_are_rejected_without_calculating_them(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('formula Excel');
        $this->service->import($this->workbook([['=HYPERLINK("https://example.com")', null, null, 10, 'pcs', null, 5]]), $user->id);
    }

    public function test_import_row_limit_is_checked_before_loading_data(): void
    {
        $user = User::factory()->create();
        $path = $this->workbook(array_fill(0, 1001, ['Kopi', null, null, 1, 'pcs', null, 5]));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('1000 baris');
        $this->service->import($path, $user->id);
    }

    public function test_corrupt_excel_signature_returns_friendly_file_validation_error(): void
    {
        $user = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'corrupt_excel_');
        $this->files[] = $path;
        file_put_contents($path, "PK\x03\x04".str_repeat("\x00", 128));

        try {
            $this->service->import($path, $user->id);
            $this->fail('Expected corrupt file rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Berkas tidak dapat dibaca', $exception->errors()['importFile'][0]);
            $this->assertStringNotContainsString($path, $exception->getMessage());
            $this->assertDatabaseCount('stocks', 0);
        }
    }

    public function test_unsafe_workbook_xml_is_reported_as_file_validation_error(): void
    {
        $user = User::factory()->create();
        $path = $this->workbook([['Kopi', null, null, 1, 'pcs', null, 5]]);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><!DOCTYPE sheet [<!ENTITY bad SYSTEM "file:///not-accessed">]><sheet>&bad;</sheet>');
        $zip->close();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Berkas tidak dapat dibaca');
        $this->service->import($path, $user->id);
    }

    public function test_xls_read_data_only_keeps_formula_type_and_import_rejects_it(): void
    {
        $user = User::factory()->create();
        $path = $this->workbook([['=1+1', null, null, 10, 'pcs', null, 5]], StockExcelService::HEADERS, true);
        $reader = IOFactory::createReaderForFile($path, ['Xls']);
        $reader->setReadDataOnly(true);
        $loaded = $reader->load($path);
        $this->assertSame(DataType::TYPE_FORMULA, $loaded->getActiveSheet()->getCell('A2')->getDataType());
        $loaded->disconnectWorksheets();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('formula Excel');
        $this->service->import($path, $user->id);
    }

    public function test_import_rolls_back_if_persistence_fails_mid_batch(): void
    {
        $user = User::factory()->create();
        $path = $this->workbook([
            ['Kopi', null, null, 12, 'pcs', null, 5],
            ['Gula', null, null, 10, 'pcs', null, 5],
        ]);
        $service = $this->createMock(StockService::class);
        $call = 0;
        $service->expects($this->exactly(2))->method('createStock')->willReturnCallback(function ($userId, $record) use (&$call) {
            if (++$call === 2) {
                throw new RuntimeException('Database failure');
            }

            return Stock::create([...$record, 'user_id' => $userId]);
        });
        try {
            (new StockExcelService($service))->import($path, $user->id);
            $this->fail('Expected persistence failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database failure', $exception->getMessage());
            $this->assertDatabaseCount('stocks', 0);
        }
    }
}
