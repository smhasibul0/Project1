<?php

namespace App\Support;

class NumberToWords
{
    /** @var array<int, string> */
    private static array $ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    /** @var array<int, string> */
    private static array $tens = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /**
     * Render an amount as words, e.g. 44000 => "Forty Four Thousand Taka Only".
     */
    public static function make(float $amount, string $currency = 'Taka'): string
    {
        $whole = (int) floor(round($amount, 2));
        $words = trim(self::convert($whole));

        if ($words === '') {
            $words = 'Zero';
        }

        return $words.' '.$currency.' Only';
    }

    private static function convert(int $n): string
    {
        if ($n < 20) {
            return self::$ones[$n];
        }
        if ($n < 100) {
            return trim(self::$tens[intdiv($n, 10)].' '.self::$ones[$n % 10]);
        }
        if ($n < 1000) {
            return trim(self::$ones[intdiv($n, 100)].' Hundred '.self::convert($n % 100));
        }

        foreach ([1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand'] as $unit => $label) {
            if ($n >= $unit) {
                return trim(self::convert(intdiv($n, $unit)).' '.$label.' '.self::convert($n % $unit));
            }
        }

        return '';
    }
}
