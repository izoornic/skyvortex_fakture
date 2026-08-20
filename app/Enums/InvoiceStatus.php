<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'nacrt';
    case Issued = 'izdata';
    case PartiallyPaid = 'delimicno_placena';
    case Paid = 'placena';
    case Cancelled = 'stornirana';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nacrt',
            self::Issued => 'Izdata',
            self::PartiallyPaid => 'Delimično plaćena',
            self::Paid => 'Plaćena',
            self::Cancelled => 'Stornirana',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Issued => 'blue',
            self::PartiallyPaid => 'amber',
            self::Paid => 'lime',
            self::Cancelled => 'red',
        };
    }

    /**
     * A draft is the only thing that may still be edited or deleted. Once a
     * document is issued it is corrected by a credit note or a storno.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Statuses that mean the document exists in the outside world and takes up
     * a number.
     */
    public function isIssued(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Paid, self::Cancelled], true);
    }

    public function countsTowardsRevenue(): bool
    {
        return $this->isIssued() && $this !== self::Cancelled;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
