<?php

namespace App\Enums;

/**
 * VAT category codes from UNTDID 5305, as used by EN 16931 and therefore by
 * the UBL invoice that phase 2 has to produce. The codes are fixed by the
 * standard, so they live in an enum rather than an editable codebook.
 */
enum VatCategory: string
{
    case Standard = 'S';
    case ZeroRated = 'Z';
    case Exempt = 'E';
    case ReverseCharge = 'AE';
    case IntraCommunity = 'K';
    case Export = 'G';
    case OutOfScope = 'O';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standardna stopa',
            self::ZeroRated => 'Nulta stopa',
            self::Exempt => 'Oslobođeno PDV-a',
            self::ReverseCharge => 'Obrnuti obračun PDV-a',
            self::IntraCommunity => 'Isporuka unutar EEA',
            self::Export => 'Izvoz, PDV se ne obračunava',
            self::OutOfScope => 'Nije predmet oporezivanja',
        };
    }

    /**
     * Categories other than the standard one must state why no VAT is charged.
     */
    public function requiresExemptionReason(): bool
    {
        return $this !== self::Standard;
    }

    /**
     * Whether a non-zero rate may be attached to this category.
     */
    public function allowsRate(): bool
    {
        return $this === self::Standard;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => "{$category->value} — {$category->label()}"])
            ->all();
    }
}
