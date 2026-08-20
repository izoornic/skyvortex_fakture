<?php

namespace App\Enums;

enum PartnerType: string
{
    case LegalEntity = 'pravno_lice';
    case Entrepreneur = 'preduzetnik';
    case Individual = 'fizicko_lice';
    case Foreign = 'strano_lice';

    public function label(): string
    {
        return match ($this) {
            self::LegalEntity => 'Pravno lice',
            self::Entrepreneur => 'Preduzetnik',
            self::Individual => 'Fizičko lice',
            self::Foreign => 'Strano lice',
        };
    }

    /**
     * Domestic company registers: PIB and matični broj are mandatory.
     */
    public function requiresTaxNumber(): bool
    {
        return in_array($this, [self::LegalEntity, self::Entrepreneur], true);
    }

    /**
     * Only natural persons carry a JMBG, and it is a personal data point.
     */
    public function allowsPersonalNumber(): bool
    {
        return $this === self::Individual;
    }

    public function isForeign(): bool
    {
        return $this === self::Foreign;
    }

    /**
     * Label of the name field, which differs for natural persons.
     */
    public function nameLabel(): string
    {
        return $this === self::Individual ? 'Ime i prezime' : 'Naziv';
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
