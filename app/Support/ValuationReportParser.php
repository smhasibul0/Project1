<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;

/**
 * Reads the "ValuationReport_Analysis" PDF that Bangladesh Customs (ASYCUDA) prints
 * for an HS code: one line per bill of entry, with the price declared and the price
 * Customs assessed, per kilogram.
 *
 * The PDF's text comes out with its columns run together — a price and the unit price
 * after it print as "50.001.00" — but every amount carries exactly two decimals, so
 * the amounts are split by shape. A bill printed under the one above it on the same
 * day leaves its date out; it takes the date of the bill above.
 */
class ValuationReportParser
{
    /**
     * One bill of entry: [office proc] BE_NO [BE_Date] HSCODE description item
     * currency, then the declared price, declared unit price, assessed price, assessed
     * unit price and net weight, then the item quantity and the country of origin.
     * The description may not run past another HS code, so the filter block at the
     * top of the report can never swallow the first bill.
     */
    private const BILL = '/(?<be>\d+)?\s*(?<date>\d{2}\/\d{2}\/\d{4})?\s*(?<hs>\d{8})(?<desc>(?:(?!\d{8}).){1,250}?)\s+(?<item>\d+)\s+(?<currency>[A-Z]{3})\s+(?<amounts>(?:[\d,]+\.\d{2}\s*){4}[\d,]+\.\d{2})\s+(?<qty>[\d,]+)\s*(?<country>[A-Z]{2})\b/u';

    private const AMOUNT = '/[\d,]+\.\d{2}/';

    /**
     * The reference prices per HS code in the report, from the unit prices Customs
     * assessed on the bills priced in US dollars: the highest, the lowest and the
     * most common.
     *
     * @return array<int, array{code_digits: string, description: string, unit_price: float, lowest_unit_price: float, common_unit_price: float, common_bills: int, period_from: string|null, period_to: string|null, bills: array<int, array<string, mixed>>}>
     *
     * @throws \RuntimeException when the file isn't a readable valuation report
     */
    public function parse(string $path): array
    {
        $bills = $this->bills($this->text($path));

        if ($bills === []) {
            throw new \RuntimeException('No bills of entry were found — is this a Customs valuation report?');
        }

        $rates = [];

        foreach (collect($bills)->groupBy('hs') as $digits => $group) {
            $usd = $group->where('currency', 'USD');

            if ($usd->isEmpty()) {
                continue;
            }

            $dates = $group->pluck('be_date')->filter()->sort()->values();

            $rates[] = [
                'code_digits' => (string) $digits,
                'description' => (string) $group->countBy('description')->sortDesc()->keys()->first(),
                ...static::summarise($usd->all()),
                'period_from' => $dates->first(),
                'period_to' => $dates->last(),
                'bills' => $group->map(fn (array $bill) => collect($bill)->except('hs')->all())->values()->all(),
            ];
        }

        if ($rates === []) {
            throw new \RuntimeException('None of the bills in this report are priced in US dollars.');
        }

        return $rates;
    }

    /**
     * The highest, lowest and most common assessed unit price across dollar bills,
     * and how many bills carry the most common one. When two prices are equally
     * common, the higher one counts.
     *
     * @param  array<int, array<string, mixed>>  $usdBills
     * @return array{unit_price: float, lowest_unit_price: float, common_unit_price: float, common_bills: int}
     */
    public static function summarise(array $usdBills): array
    {
        $prices = collect($usdBills)->map(fn (array $bill): float => round((float) $bill['assessed_unit_price'], 2));

        $common = $prices
            ->countBy(fn (float $price): string => number_format($price, 2, '.', ''))
            ->map(fn (int $count, string $price): array => ['price' => (float) $price, 'count' => $count])
            ->sort(fn (array $a, array $b): int => [$b['count'], $b['price']] <=> [$a['count'], $a['price']])
            ->first();

        return [
            'unit_price' => (float) $prices->max(),
            'lowest_unit_price' => (float) $prices->min(),
            'common_unit_price' => $common['price'],
            'common_bills' => $common['count'],
        ];
    }

    private function text(string $path): string
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException('The file could not be read as a PDF.', 0, $e);
        }

        // Descriptions and exporter names wrap over several lines; read it as one line.
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bills(string $text): array
    {
        preg_match_all(self::BILL, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $bills = [];
        $date = null;

        foreach ($matches as $index => $match) {
            preg_match_all(self::AMOUNT, $match['amounts'][0], $amounts);

            if (count($amounts[0]) !== 5) {
                continue;
            }

            [$declaredPrice, $declaredUnit, $assessedPrice, $assessedUnit, $netWeight] = array_map(
                fn (string $amount): float => (float) str_replace(',', '', $amount),
                $amounts[0]
            );

            if (($match['date'][0] ?? '') !== '') {
                $date = Carbon::createFromFormat('d/m/Y', $match['date'][0])->toDateString();
            }

            // The exporter's name runs on until the next bill (or the next page's header).
            $end = isset($matches[$index + 1]) ? $matches[$index + 1][0][1] : strlen($text);
            $start = $match[0][1] + strlen($match[0][0]);
            $exporter = trim(preg_split('/\s(?:\d{3} \d |OfficeType)/', substr($text, $start, $end - $start))[0]);

            $bills[] = [
                'hs' => $match['hs'][0],
                'be_no' => $match['be'][0] !== '' ? $match['be'][0] : null,
                'be_date' => $date,
                'description' => trim($match['desc'][0]),
                'currency' => $match['currency'][0],
                'declared_price' => $declaredPrice,
                'declared_unit_price' => $declaredUnit,
                'assessed_price' => $assessedPrice,
                'assessed_unit_price' => $assessedUnit,
                'net_weight' => $netWeight,
                'country' => $match['country'][0],
                'exporter' => mb_substr($exporter, 0, 80),
            ];
        }

        return $bills;
    }
}
