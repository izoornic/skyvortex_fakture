<?php

namespace App\Enums;

/**
 * What kind of entity issues the documents.
 *
 * Every one of them carries a PIB and a matični broj, so the type changes no
 * validation — it is here because a stambena zajednica is not a company, and a
 * list that calls it one is misleading to whoever reads it.
 */
enum CompanyType: string
{
    case LegalEntity = 'pravno_lice';
    case Entrepreneur = 'preduzetnik';
    case HousingCommunity = 'stambena_zajednica';

    public function label(): string
    {
        return match ($this) {
            self::LegalEntity => 'Pravno lice',
            self::Entrepreneur => 'Preduzetnik',
            self::HousingCommunity => 'Stambena zajednica',
        };
    }

    /**
     * A stambena zajednica has no line of business to declare, and it is never a
     * user of public funds — those two fields have nothing to say about it.
     */
    public function hasActivityCode(): bool
    {
        return $this !== self::HousingCommunity;
    }

    public function canBePublicFunds(): bool
    {
        return $this !== self::HousingCommunity;
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
