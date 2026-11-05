<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Models\Stock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StockExcelService
{
    public const HEADERS = ['name', 'sku', 'description', 'quantity', 'unit', 'price', 'min_stock', 'status', 'created_at'];

    public function __construct(protected StockService $stockService) {}

    /** @param Collection<int, Stock> $stocks */
    public function buildSpreadsheet(Collection $stocks, string $sheetTitle = 'Stocks'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('153E35');
        $sheet->freezePane('A2');

        foreach ($stocks->values() as $index => $stock) {
            $row = $index + 2;
            foreach (['A' => $stock->name, 'B' => $stock->sku, 'C' => $stock->description, 'E' => $stock->unit, 'H' => $stock->status, 'I' => $stock->created_at?->format('Y-m-d H:i:s')] as $column => $value) {
                $sheet->setCellValueExplicit($column.$row, $value ?? '', DataType::TYPE_STRING);
            }
            $sheet->setCellValue('D'.$row, $stock->quantity);
            $sheet->setCellValue('F'.$row, $stock->price);
            $sheet->setCellValue('G'.$row, $stock->min_stock);
        }

        $lastRow = max(2, $stocks->count() + 1);
        $sheet->setAutoFilter('A1:I'.$lastRow);
        $sheet->getStyle('D2:D'.$lastRow)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('F2:F'.$lastRow)->getNumberFormat()->setFormatCode('"Rp" #,##0.00');
        $sheet->getStyle('G2:G'.$lastRow)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('C2:C'.$lastRow)->getAlignment()->setWrapText(true);
        foreach (['A' => 32, 'B' => 20, 'C' => 48, 'D' => 14, 'E' => 12, 'F' => 22, 'G' => 14, 'H' => 20, 'I' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return $spreadsheet;
    }

    /** @param Collection<int, Stock> $stocks */
    public function exportToResponse(Collection $stocks, string $filename = 'stocks.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($stocks);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{imported: int} */
    public function import(string $filePath, int $userId): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath, ['Xlsx', 'Xls', 'Csv']);
            $reader->setReadDataOnly(true);
            $info = $reader->listWorksheetInfo($filePath);
            if ($info[0]['totalRows'] > 1001) {
                throw ValidationException::withMessages(['importFile' => 'Maksimal 1000 baris stok dalam satu berkas.']);
            }
            $reader->setLoadSheetsOnly($info[0]['worksheetName']);
            $spreadsheet = $reader->load($filePath);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['importFile' => 'Berkas tidak dapat dibaca. Unggah berkas XLSX, XLS, atau CSV yang valid dan tidak rusak.']);
        }
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = $sheet->getHighestDataRow();
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $headerRow) {
            foreach ($headerRow->getCellIterator() as $cell) {
                $headers[strtolower(trim((string) $cell->getValue()))] = $cell->getColumn();
            }
        }
        $fields = ['name', 'sku', 'description', 'quantity', 'unit', 'price', 'min_stock'];
        if (array_diff($fields, array_keys($headers)) !== []) {
            throw ValidationException::withMessages(['importFile' => 'Header wajib: name, sku, description, quantity, unit, price, min_stock. Gunakan hasil ekspor sebagai template.']);
        }

        $records = [];
        for ($row = 2; $row <= $lastRow; $row++) {
            $data = [];
            foreach ($fields as $field) {
                $cell = $sheet->getCell($headers[$field].$row);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw ValidationException::withMessages(['importFile' => "Baris {$row}: formula Excel tidak diizinkan."]);
                }
                $value = $cell->getValue();
                $data[$field] = is_string($value) ? trim($value) : $value;
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
            if (count(array_filter($data, fn ($value) => $value !== null)) === 0) {
                continue;
            }
            $validator = Validator::make($data, StockService::rules());
            if ($validator->fails()) {
                throw ValidationException::withMessages(['importFile' => "Baris {$row}: ".$validator->errors()->first()]);
            }
            $records[] = $validator->validated();
        }
        $spreadsheet->disconnectWorksheets();

        DB::transaction(function () use ($records, $userId): void {
            foreach ($records as $record) {
                $this->stockService->createStock($userId, $record);
            }
        });

        return ['imported' => count($records)];
    }
}
