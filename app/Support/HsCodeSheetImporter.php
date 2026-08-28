<?php

namespace App\Support;

use App\Models\HsCode;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Loads a customs tariff workbook into the hs_codes table.
 *
 * Two shapes are supported and detected from the header row:
 *  - the published Bangladesh Customs tariff (Heading / H.S. Code / Description /
 *    Statistical Unit / Statutory Rate of Customs Duty on Import), where the
 *    description of a line is spread across its heading and dash-indented parents;
 *  - a flat rates sheet (HS Code + any of CD / SD / VAT / AIT / RD / AT), which
 *    merges onto rows that already exist instead of creating new ones.
 */
class HsCodeSheetImporter
{
    /** Rows scanned while looking for the header row. */
    private const HEADER_SCAN_ROWS = 40;

    private const CHUNK = 500;

    /** A tariff line: 3911.90.00, or 3911.90 for a six-digit subheading. */
    private const CODE_PATTERN = '/^\d{4}\.?\d{2}(\.?\d{2})?$/';

    /**
     * Normalized header label => field. Matching is exact on the normalized
     * label so "…Customs Duty on Export" never lands on the import column.
     *
     * @var array<string, array<int, string>>
     */
    private const HEADERS = [
        'code' => ['hscode', 'hscodes', 'hstcode', 'tariffcode', 'code', 'hs'],
        'heading' => ['heading', 'hsheading'],
        'description' => ['description', 'descriptionofgoods', 'goodsdescription', 'itemdescription', 'descriptionofitem'],
        'statistical_unit' => ['statisticalunit', 'statunit', 'unit'],
        'cd_rate' => ['cd', 'cdrate', 'customsduty', 'customsdutyrate', 'statutoryrateofcustomsdutyonimport', 'rateofcustomsdutyonimport'],
        'sd_rate' => ['sd', 'sdrate', 'supplementaryduty'],
        'vat_rate' => ['vat', 'vatrate', 'valueaddedtax'],
        'ait_rate' => ['ait', 'aitrate', 'advanceincometax'],
        'rd_rate' => ['rd', 'rdrate', 'regulatoryduty'],
        'at_rate' => ['at', 'atrate', 'advancetax', 'advancetradevat', 'atv'],
    ];

    /**
     * Read the workbook and write it to hs_codes.
     *
     * @return array{created: int, updated: int, skipped: int, mode: string, columns: array<int, string>}
     *
     * @throws \RuntimeException when the sheet has no recognizable header row
     */
    public function import(string $path): array
    {
        if ((int) ini_get('memory_limit') !== -1) {
            ini_set('memory_limit', '512M');
        }

        $columns = $this->detectColumns($path);
        $records = $this->readRecords($path, $columns);

        if ($records === []) {
            throw new \RuntimeException('No tariff lines were found under the header row.');
        }

        $fields = array_values(array_diff(array_keys($columns), ['heading']));
        $mode = isset($columns['description']) ? 'full' : 'merge';

        $stats = $mode === 'full'
            ? $this->upsertRecords($records)
            : $this->mergeRates($records);

        return $stats + ['mode' => $mode, 'columns' => $fields];
    }

    /**
     * Locate the header row and map each field to its column index.
     *
     * @return array<string, int>
     */
    private function detectColumns(string $path): array
    {
        $rows = $this->load($path, $this->rowLimitFilter(self::HEADER_SCAN_ROWS))
            ->rangeToArray('A1:ZZ'.self::HEADER_SCAN_ROWS, null, true, false, false);

        foreach ($rows as $row) {
            $mapped = [];

            foreach ($row as $index => $value) {
                $label = strtolower(preg_replace('/[^a-z]/i', '', (string) $value) ?? '');

                if ($label === '') {
                    continue;
                }

                foreach (self::HEADERS as $field => $aliases) {
                    if (in_array($label, $aliases, true)) {
                        $mapped[$field] ??= $index;
                    }
                }
            }

            // A usable header row identifies the code plus something to store against it.
            if (isset($mapped['code']) && count($mapped) > 1) {
                return $mapped;
            }
        }

        throw new \RuntimeException('Could not find a header row containing an "HS Code" column.');
    }

    /**
     * Walk the sheet below the header, resolving each coded line's full description
     * from the heading and dash-indented parents standing above it.
     *
     * The published book is a PDF conversion: a line's code sits in whichever
     * column that page used, page headers repeat every few dozen rows, and a
     * handful of cells hold two lines stacked with newlines. Every cell is
     * therefore split into lines and the code is looked for by shape, not position.
     *
     * @param  array<string, int>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function readRecords(string $path, array $columns): array
    {
        $sheet = $this->load($path, $this->columnFilter($columns));
        $lastColumn = Coordinate::stringFromColumnIndex(max($columns) + 1);
        $rows = $sheet->rangeToArray('A1:'.$lastColumn.$sheet->getHighestDataRow(), null, true, false, false);

        // A code never sits to the right of the description it belongs to.
        $codeSearchLimit = $columns['description'] ?? $columns['code'];
        $dottedOnly = isset($columns['heading']);

        $records = [];
        $bare = [];
        $headingDescription = null;
        $parents = [];

        foreach ($rows as $row) {
            $codes = $this->codesIn($row, $codeSearchLimit, $dottedOnly);
            $descriptions = $this->linesAt($row, $columns['description'] ?? null);
            $units = $this->linesAt($row, $columns['statistical_unit'] ?? null);

            $rates = [];
            foreach (HsCode::RATE_FIELDS as $field) {
                $rates[$field] = isset($columns[$field]) ? $this->linesAt($row, $columns[$field]) : [];
            }

            $next = 0;
            $emit = function (string $text = '', int $level = 0) use (
                &$records, &$bare, &$next, $codes, $units, $rates, $columns,
                &$headingDescription, &$parents
            ): void {
                $code = $codes[$next] ?? null;

                if ($code === null) {
                    return;
                }

                $digits = HsCode::digits($code);
                $record = ['code' => $code, 'code_digits' => $digits];

                if (isset($columns['description'])) {
                    $lineage = $level === 0 ? [] : array_filter(
                        $parents,
                        fn (int $parentLevel): bool => $parentLevel < $level,
                        ARRAY_FILTER_USE_KEY
                    );

                    if ($level > 0 && $headingDescription !== null) {
                        array_unshift($lineage, $headingDescription);
                    }

                    $lineage[] = $text;
                    $record['description'] = implode(' — ', array_filter($lineage, fn (string $part): bool => $part !== ''));
                }

                if (isset($columns['statistical_unit'])) {
                    $record['statistical_unit'] = $this->pick($units, $next) ?: null;
                }

                foreach (HsCode::RATE_FIELDS as $field) {
                    if (! isset($columns[$field])) {
                        continue;
                    }

                    [$value, $isPercent] = $this->parseRate((string) $this->pick($rates[$field], $next));
                    $record[$field] = $value;

                    if ($value !== null && ! $isPercent) {
                        $bare[$digits][$field] = true;
                    }
                }

                $records[$digits] = $record;
                $next++;
            };

            // No description column at all (a rates-only sheet): the codes are the rows.
            if (! isset($columns['description'])) {
                while (isset($codes[$next])) {
                    $emit();
                }

                continue;
            }

            $entries = $this->foldLines($descriptions);
            $lastIndex = count($entries) - 1;

            foreach ($entries as $index => $entry) {
                ['level' => $level, 'text' => $text] = $entry;

                // An indented line names a tariff line; an un-indented one is the
                // heading above them — unless it is the last thing on a row that
                // still has a code waiting, in which case it names that code.
                if (isset($codes[$next]) && ($level > 0 || $index === $lastIndex)) {
                    $emit($text, $level);

                    if ($level === 0) {
                        $headingDescription = $text;
                        $parents = [];
                    }

                    continue;
                }

                if ($level === 0) {
                    $headingDescription = $text;
                    $parents = [];
                } else {
                    $parents = array_filter($parents, fn (int $l): bool => $l < $level, ARRAY_FILTER_USE_KEY);
                    $parents[$level] = $text;
                }
            }

            // Codes left over on a row whose description cell ran short.
            while (isset($codes[$next])) {
                $emit($headingDescription ?? '', 1);
            }
        }

        return array_values($this->scaleFractionalRates($records, $bare));
    }

    /**
     * The tariff book prints duty as a fraction (0.05 for 5%) while the pages
     * converted from a different template write "10%". If every unsigned value in
     * a column stays at or below 1, that column is fractions — scale it to percent
     * and leave the cells that already carried a % sign alone.
     *
     * @param  array<string, array<string, mixed>>  $records
     * @param  array<string, array<string, bool>>  $bare
     * @return array<string, array<string, mixed>>
     */
    private function scaleFractionalRates(array $records, array $bare): array
    {
        foreach (HsCode::RATE_FIELDS as $field) {
            $values = [];

            foreach ($records as $digits => $record) {
                if (isset($bare[$digits][$field]) && $record[$field] !== null) {
                    $values[] = $record[$field];
                }
            }

            if ($values === [] || max($values) > 1 || max($values) <= 0) {
                continue;
            }

            foreach ($records as $digits => $record) {
                if (isset($bare[$digits][$field]) && $record[$field] !== null) {
                    $records[$digits][$field] = round($record[$field] * 100, 2);
                }
            }
        }

        return $records;
    }

    /**
     * Every tariff code found on a row, in column order, looking only left of the
     * description. Cells are split on newlines because merged rows stack two codes.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    private function codesIn(array $row, int $limit, bool $dottedOnly): array
    {
        $codes = [];

        foreach ($row as $index => $value) {
            if ($index > $limit || $value === null) {
                continue;
            }

            foreach ($this->lines((string) $value) as $line) {
                if (! preg_match(self::CODE_PATTERN, $line)) {
                    continue;
                }

                // In the published book a code always carries its dots; a bare run
                // of digits there is a serial number from a schedule table.
                if ($dottedOnly && ! str_contains($line, '.')) {
                    continue;
                }

                if (strlen(HsCode::digits($line)) >= 6) {
                    $codes[] = $line;
                }
            }
        }

        return $codes;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    private function linesAt(array $row, ?int $index): array
    {
        return $index === null || ! isset($row[$index]) ? [] : $this->lines((string) $row[$index]);
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $value) ?: []),
            fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * Turn the lines of a description cell into tariff entries.
     *
     * A long heading is wrapped across several lines in the same cell, so a line
     * that carries no dashes and reads as a continuation of the one above it —
     * the previous line stopped mid-sentence and this one opens lower-case — is
     * joined back on instead of being taken for a heading of its own.
     *
     * @param  array<int, string>  $lines
     * @return array<int, array{level: int, text: string}>
     */
    private function foldLines(array $lines): array
    {
        $entries = [];
        $previous = null;

        foreach ($lines as $line) {
            if (! $this->isDescription($line)) {
                $previous = null;

                continue;
            }

            $level = $this->dashLevel($line);
            $text = $this->clean($line);
            $continues = $level === 0
                && $entries !== []
                && $previous !== null
                && ! preg_match('/[.:;]$/u', $previous)
                && ! preg_match('/^\p{Lu}/u', $line);

            if ($continues) {
                $entries[array_key_last($entries)]['text'] = trim($entries[array_key_last($entries)]['text'].' '.$text);
            } elseif ($text !== '') {
                $entries[] = ['level' => $level, 'text' => $text];
            }

            $previous = $line;
        }

        return array_values($entries);
    }

    /**
     * A stacked cell pairs line-for-line with the codes; a single value covers them all.
     *
     * @param  array<int, string>  $lines
     */
    private function pick(array $lines, int $index): ?string
    {
        return $lines[$index] ?? (count($lines) === 1 ? $lines[0] : null);
    }

    /**
     * Reject the column headings the book repeats at the top of every page, and
     * any cell with nothing readable in it.
     */
    private function isDescription(string $line): bool
    {
        if (! preg_match('/\p{L}/u', $line)) {
            return false;
        }

        $label = strtolower(preg_replace('/[^a-z]/i', '', $line) ?? '');

        foreach (self::HEADERS as $aliases) {
            if (in_array($label, $aliases, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Insert new tariff lines and refresh the ones already stored.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array{created: int, updated: int, skipped: int}
     */
    private function upsertRecords(array $records): array
    {
        $existing = $this->existingDigits(array_column($records, 'code_digits'));
        $created = 0;
        $updated = 0;
        $now = now();

        foreach (array_chunk($records, self::CHUNK) as $chunk) {
            $payload = [];

            foreach ($chunk as $record) {
                $isNew = ! isset($existing[$record['code_digits']]);
                $isNew ? $created++ : $updated++;

                // A rate the sheet leaves blank is nothing to pay, not an unknown.
                foreach (HsCode::RATE_FIELDS as $field) {
                    if (array_key_exists($field, $record)) {
                        $record[$field] ??= 0;
                    }
                }

                $payload[] = $record + [
                    'description' => '',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            HsCode::upsert($payload, ['code_digits'], array_values(array_diff(
                array_keys($payload[0]),
                ['code_digits', 'created_at', 'is_active']
            )));
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => 0];
    }

    /**
     * A rates-only sheet tops up existing lines; codes we don't know are skipped
     * rather than inserted with a blank description.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array{created: int, updated: int, skipped: int}
     */
    private function mergeRates(array $records): array
    {
        $existing = $this->existingDigits(array_column($records, 'code_digits'));
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($records, $existing, &$updated, &$skipped) {
            foreach ($records as $record) {
                $id = $existing[$record['code_digits']] ?? null;

                if ($id === null) {
                    $skipped++;

                    continue;
                }

                $fields = array_filter(
                    array_intersect_key($record, array_flip([...HsCode::RATE_FIELDS, 'statistical_unit'])),
                    fn ($value): bool => $value !== null
                );

                if ($fields !== []) {
                    HsCode::whereKey($id)->update($fields);
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        return ['created' => 0, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, string>  $digits
     * @return array<string, int> code_digits => id
     */
    private function existingDigits(array $digits): array
    {
        $map = [];

        foreach (array_chunk($digits, 1000) as $chunk) {
            $map += HsCode::whereIn('code_digits', $chunk)->pluck('id', 'code_digits')->all();
        }

        return $map;
    }

    /**
     * Depth of a dash-indented tariff description ("-- Other" is level 2).
     */
    private function dashLevel(string $description): int
    {
        preg_match('/^-+/', $description, $matches);

        return isset($matches[0]) ? strlen($matches[0]) : 0;
    }

    /**
     * Drop the leading dashes and the trailing colon/full stop the tariff book uses.
     */
    private function clean(string $description): string
    {
        $description = preg_replace('/\s+/u', ' ', $description) ?? $description;

        return trim(trim(ltrim($description, "- \t"), " .:\u{FF1A}"));
    }

    /**
     * Read one rate cell.
     *
     * "Free" is nothing to pay. A specific duty ("BDT 3000 per MT") cannot be
     * charged on value, so it books as zero and is keyed in by hand. Anything
     * written with a % sign is already a percentage and is returned as one; a
     * bare number is reported so the column can be scaled if it holds fractions.
     *
     * @return array{0: float|null, 1: bool} value, already a percentage
     */
    private function parseRate(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [null, false];
        }

        if (preg_match('/^(free|nil|exempt)/i', $value)) {
            return [0.0, true];
        }

        $isPercent = str_contains($value, '%');
        $numeric = preg_replace('/[^0-9.]/', '', explode('%', $value)[0]);

        if (! is_numeric($numeric)) {
            return [null, false];
        }

        // A duty quoted per stick, per litre or per tonne is not an ad valorem rate.
        if (preg_match('/\b(bdt|tk|taka|usd|per)\b/i', $value)) {
            return [0.0, true];
        }

        return [(float) $numeric, $isPercent];
    }

    /**
     * Load one worksheet, reading only the cells the filter allows so a
     * 11,000-line tariff book doesn't have to fit in memory whole.
     */
    private function load(string $path, IReadFilter $filter): Worksheet
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter($filter);

            return $reader->load($path)->getActiveSheet();
        } catch (ReaderException $e) {
            throw new \RuntimeException('The file could not be read as a spreadsheet.', 0, $e);
        }
    }

    private function rowLimitFilter(int $lastRow): IReadFilter
    {
        return new class($lastRow) implements IReadFilter
        {
            public function __construct(private int $lastRow) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->lastRow;
            }
        };
    }

    /**
     * @param  array<string, int>  $columns
     */
    private function columnFilter(array $columns): IReadFilter
    {
        $allowed = [];

        foreach ($columns as $index) {
            $allowed[Coordinate::stringFromColumnIndex($index + 1)] = true;
        }

        return new class($allowed) implements IReadFilter
        {
            /** @param array<string, bool> $allowed */
            public function __construct(private array $allowed) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return isset($this->allowed[$columnAddress]);
            }
        };
    }
}
