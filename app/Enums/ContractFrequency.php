<?php

namespace App\Enums;

/**
 * How often a contract produces a document.
 *
 * Only monthly for now, deliberately: `invoices` carries `period_month`, so a
 * quarterly document would have to pick one month to call its period, and both
 * the monthly overview and the printed period would then be lying. That is a
 * decision to make when a quarterly contract actually turns up.
 */
enum ContractFrequency: string
{
    case Monthly = 'mesecno';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mesečno',
        };
    }

    /**
     * Months between two documents.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $frequency) => [$frequency->value => $frequency->label()])
            ->all();
    }
}
