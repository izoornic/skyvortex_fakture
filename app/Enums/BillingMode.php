<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Which month a contract bills when it generates a draft.
 *
 * Maintenance and consulting are billed once the month is behind you; rent and
 * subscriptions are billed before it starts. Some arrangements lag a further
 * month, so that September's run bills July. All three are ordinary, so the
 * contract carries the choice rather than the application.
 */
enum BillingMode: string
{
    case Arrears = 'unazad';
    case ArrearsTwoMonths = 'unazad-2';
    case Advance = 'unapred';

    public function label(): string
    {
        return match ($this) {
            self::Arrears => 'Unazad — mesec koji je istekao',
            self::ArrearsTwoMonths => 'Dva meseca unazad — pretprošli mesec',
            self::Advance => 'Unapred — mesec koji dolazi',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Arrears => 'Unazad',
            self::ArrearsTwoMonths => 'Dva unazad',
            self::Advance => 'Unapred',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Arrears => 'Promet je poslednji dan tog meseca. Za usluge koje se obračunavaju po isteku.',
            self::ArrearsTwoMonths => 'Promet je poslednji dan tog meseca. Za obračun koji kasni ceo mesec — u septembru se fakturiše jul.',
            self::Advance => 'Promet je prvi dan tog meseca. Za zakup, pretplatu i održavanje plaćeno unapred.',
        };
    }

    /**
     * The month being billed, given the day the draft is generated.
     */
    public function periodFor(Carbon $generatedOn): Carbon
    {
        return match ($this) {
            self::Arrears => $generatedOn->copy()->startOfMonth()->subMonth(),
            self::ArrearsTwoMonths => $generatedOn->copy()->startOfMonth()->subMonths(2),
            self::Advance => $generatedOn->copy()->startOfMonth(),
        };
    }

    /**
     * The date the goods or service are considered supplied. It decides the tax
     * period, so it belongs to the month being billed — never to the day the
     * draft happened to be made.
     */
    public function supplyDateFor(Carbon $period): Carbon
    {
        return match ($this) {
            self::Arrears, self::ArrearsTwoMonths => $period->copy()->endOfMonth()->startOfDay(),
            self::Advance => $period->copy()->startOfMonth(),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])
            ->all();
    }
}
