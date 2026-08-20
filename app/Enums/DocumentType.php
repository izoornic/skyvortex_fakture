<?php

namespace App\Enums;

enum DocumentType: string
{
    case Invoice = 'faktura';
    case AdvanceInvoice = 'avansna_faktura';
    case CreditNote = 'knjizno_odobrenje';
    case DebitNote = 'knjizno_zaduzenje';
    case Proforma = 'predracun';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Faktura',
            self::AdvanceInvoice => 'Avansna faktura',
            self::CreditNote => 'Knjižno odobrenje',
            self::DebitNote => 'Knjižno zaduženje',
            self::Proforma => 'Predračun',
        };
    }

    /**
     * Prefix in the document number. Plain invoices carry none, so their
     * number stays 2026-0001; every other type is prefixed to avoid collisions.
     */
    public function numberPrefix(): string
    {
        return match ($this) {
            self::Invoice => '',
            self::AdvanceInvoice => 'AV-',
            self::CreditNote => 'KO-',
            self::DebitNote => 'KZ-',
            self::Proforma => 'PR-',
        };
    }

    /**
     * Default number template for the type, e.g. AV-2026-0001.
     */
    public function numberTemplate(): string
    {
        return $this->numberPrefix().'{godina}-{broj}';
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
