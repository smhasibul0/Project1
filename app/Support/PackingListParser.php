<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class PackingListParser
{
    /**
     * Header labels (normalized) mapped to our field keys. The supplier sheets
     * use merged bilingual headers, so matching is done per-cell after
     * stripping spaces, punctuation and case.
     *
     * @var array<string, string>
     */
    private const HEADER_MAP = [
        'mark' => 'mark',
        'ctno' => 'carton_no',
        'descriptionofitem' => 'description',
        'description' => 'description',
        'qtypkg' => 'qty_per_pkg',
        'totalqty' => 'total_qty',
        'gw' => 'gross_weight',
        'totalgw' => 'total_gross_weight',
        'nw' => 'net_weight',
        'totalnw' => 'total_net_weight',
        'cbm' => 'cbm',
        'totalcbm' => 'total_cbm',
        'remark' => 'remark',
    ];

    /**
     * Parse a supplier packing list workbook into grouped quotation-ready rows.
     *
     * @return array{
     *     customer: ?string,
     *     supplier: ?string,
     *     items: array<int, array{description: string, cartons: int, quantity: float, gross_weight: float, net_weight: float, cbm: float, remark: ?string}>,
     *     totals: array{cartons: int, quantity: float, gross_weight: float, net_weight: float, cbm: float},
     *     sheet_totals: array{quantity: float, gross_weight: float, net_weight: float, cbm: float}|null
     * }
     *
     * @throws \RuntimeException when no recognizable packing list table is found
     */
    public function parse(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (ReaderException $e) {
            throw new \RuntimeException('The file could not be read as a spreadsheet.', 0, $e);
        }

        $columns = null;
        $headerRowIndex = null;

        foreach ($rows as $index => $row) {
            $mapped = $this->mapHeaderRow($row);

            // The real header row carries at least a description and a quantity column.
            if (isset($mapped['description']) && (isset($mapped['total_qty']) || isset($mapped['qty_per_pkg']))) {
                $columns = $mapped;
                $headerRowIndex = $index;
                break;
            }
        }

        if ($columns === null) {
            throw new \RuntimeException('Could not find the packing list header row (MARK / C/T NO / DESCRIPTION...).');
        }

        [$grouped, $sheetTotals] = $this->collectItems($rows, $headerRowIndex, $columns);

        if ($grouped === []) {
            throw new \RuntimeException('No item rows were found under the packing list header.');
        }

        $totals = ['cartons' => 0, 'quantity' => 0.0, 'gross_weight' => 0.0, 'net_weight' => 0.0, 'cbm' => 0.0];
        foreach ($grouped as $item) {
            $totals['cartons'] += $item['cartons'];
            $totals['quantity'] += $item['quantity'];
            $totals['gross_weight'] += $item['gross_weight'];
            $totals['net_weight'] += $item['net_weight'];
            $totals['cbm'] += $item['cbm'];
        }
        foreach (['quantity', 'gross_weight', 'net_weight'] as $key) {
            $totals[$key] = round($totals[$key], 3);
        }
        $totals['cbm'] = round($totals['cbm'], 4);

        return [
            'customer' => $this->metadataValue($rows, ['customer name', '客户名字']),
            'supplier' => $this->metadataValue($rows, ['supplier', '供应商名称']),
            'items' => array_values($grouped),
            'totals' => $totals,
            'sheet_totals' => $sheetTotals,
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, int> field key => column index
     */
    private function mapHeaderRow(array $row): array
    {
        $mapped = [];

        foreach ($row as $col => $value) {
            $normalized = strtolower(preg_replace('/[^a-z]/i', '', (string) $value) ?? '');

            if ($normalized !== '' && isset(self::HEADER_MAP[$normalized])) {
                $mapped[self::HEADER_MAP[$normalized]] ??= $col;
            }
        }

        return $mapped;
    }

    /**
     * Walk the carton rows under the header, grouping identical descriptions,
     * until the sheet's TOTAL row (returned separately for cross-checking).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $columns
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, float>|null}
     */
    private function collectItems(array $rows, int $headerRowIndex, array $columns): array
    {
        $grouped = [];
        $sheetTotals = null;

        $cell = function (array $row, string $key) use ($columns): ?string {
            $col = $columns[$key] ?? null;

            return $col !== null && isset($row[$col]) ? trim((string) $row[$col]) : null;
        };

        foreach (array_slice($rows, $headerRowIndex + 1, null, true) as $row) {
            $rowText = implode(' ', array_map(fn ($v) => (string) $v, $row));

            if (stripos($rowText, 'total') !== false || str_contains($rowText, '合计')) {
                $sheetTotals = [
                    'quantity' => $this->toFloat($cell($row, 'total_qty') ?? $cell($row, 'qty_per_pkg')),
                    'gross_weight' => $this->toFloat($cell($row, 'total_gross_weight') ?? $cell($row, 'gross_weight')),
                    'net_weight' => $this->toFloat($cell($row, 'total_net_weight') ?? $cell($row, 'net_weight')),
                    'cbm' => $this->toFloat($cell($row, 'total_cbm') ?? $cell($row, 'cbm')),
                ];
                break;
            }

            $description = $cell($row, 'description');
            $quantity = $this->toFloat($cell($row, 'total_qty') ?? $cell($row, 'qty_per_pkg'));

            // Skip the translated header row and any spacer rows.
            if ($description === null || $description === '' || $quantity <= 0 || ! preg_match('/[a-z0-9]/i', $description)) {
                continue;
            }

            $key = mb_strtolower($description);
            $remark = $cell($row, 'remark');

            $grouped[$key] ??= [
                'description' => $description,
                'cartons' => 0,
                'quantity' => 0.0,
                'gross_weight' => 0.0,
                'net_weight' => 0.0,
                'cbm' => 0.0,
                'remark' => null,
            ];

            $grouped[$key]['cartons']++;
            $grouped[$key]['quantity'] += $quantity;
            $grouped[$key]['gross_weight'] += $this->toFloat($cell($row, 'total_gross_weight') ?? $cell($row, 'gross_weight'));
            $grouped[$key]['net_weight'] += $this->toFloat($cell($row, 'total_net_weight') ?? $cell($row, 'net_weight'));
            $grouped[$key]['cbm'] += $this->toFloat($cell($row, 'total_cbm') ?? $cell($row, 'cbm'));

            if ($remark !== null && $remark !== '' && $grouped[$key]['remark'] === null) {
                $grouped[$key]['remark'] = $remark;
            }
        }

        foreach ($grouped as &$item) {
            $item['quantity'] = round($item['quantity'], 3);
            $item['gross_weight'] = round($item['gross_weight'], 3);
            $item['net_weight'] = round($item['net_weight'], 3);
            $item['cbm'] = round($item['cbm'], 4);
        }

        return [$grouped, $sheetTotals];
    }

    /**
     * Pull a labelled value (e.g. "Customer Name): John") out of the sheet's header block.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $labels
     */
    private function metadataValue(array $rows, array $labels): ?string
    {
        foreach (array_slice($rows, 0, 12) as $row) {
            foreach ($row as $value) {
                $text = trim((string) $value);

                foreach ($labels as $label) {
                    if ($text !== '' && mb_stripos($text, $label) !== false) {
                        // Value follows the last colon (ASCII or full-width).
                        $parts = preg_split('/[:：]/u', $text);

                        if (count($parts) > 1) {
                            $candidate = trim((string) end($parts));

                            if ($candidate !== '') {
                                return $candidate;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function toFloat(?string $value): float
    {
        return (float) str_replace([',', ' '], '', (string) $value);
    }
}
