<?php

namespace App\Support;

class DutyCalculator
{
    /**
     * Landing charge applied on top of the CIF value when deriving the assessable value.
     */
    public const LANDING_CHARGE_RATE = 0.01;

    /**
     * Compute the Bangladesh Customs duty cascade for one assessable value.
     *
     * CD & RD apply on the assessable value; SD compounds on AV+CD+RD;
     * VAT and AT compound on AV+CD+RD+SD; AIT applies on the AV alone.
     *
     * @param  array{cd_rate?: float|string|null, rd_rate?: float|string|null, sd_rate?: float|string|null, vat_rate?: float|string|null, ait_rate?: float|string|null, at_rate?: float|string|null}  $rates  Percentages (e.g. 25 for 25%)
     * @return array{cd: float, rd: float, sd: float, vat: float, ait: float, at: float, total: float}
     */
    public static function calculate(float $assessableValue, array $rates): array
    {
        $pct = fn (string $key): float => ((float) ($rates[$key] ?? 0)) / 100;

        $cd = round($assessableValue * $pct('cd_rate'), 2);
        $rd = round($assessableValue * $pct('rd_rate'), 2);
        $sd = round(($assessableValue + $cd + $rd) * $pct('sd_rate'), 2);
        $vat = round(($assessableValue + $cd + $rd + $sd) * $pct('vat_rate'), 2);
        $ait = round($assessableValue * $pct('ait_rate'), 2);
        $at = round(($assessableValue + $cd + $rd + $sd) * $pct('at_rate'), 2);

        return [
            'cd' => $cd,
            'rd' => $rd,
            'sd' => $sd,
            'vat' => $vat,
            'ait' => $ait,
            'at' => $at,
            'total' => round($cd + $rd + $sd + $vat + $ait + $at, 2),
        ];
    }

    /**
     * Default assessable value for a line: goods cost plus the 1% landing charge.
     */
    public static function defaultAssessableValue(float $goodsCost): float
    {
        return round($goodsCost * (1 + self::LANDING_CHARGE_RATE), 2);
    }
}
