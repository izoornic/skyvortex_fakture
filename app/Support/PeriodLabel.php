<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Month names in Serbian, in one place — the invoice, the contract and the
 * monthly run all name the same period and must name it identically.
 */
class PeriodLabel
{
    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'januar', 'februar', 'mart', 'april', 'maj', 'jun',
        'jul', 'avgust', 'septembar', 'oktobar', 'novembar', 'decembar',
    ];

    public static function for(?int $year, ?int $month): string
    {
        return trim((self::MONTHS[$month] ?? '').' '.($year ? $year.'.' : ''));
    }

    public static function forDate(Carbon $date): string
    {
        return self::for($date->year, $date->month);
    }

    /**
     * Just the month, for headings that already carry the year.
     */
    public static function month(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public static function months(): array
    {
        return self::MONTHS;
    }
}
