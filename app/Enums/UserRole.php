<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Bookkeeper = 'knjigovodja';

    /**
     * Human readable label, shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Bookkeeper => 'Knjigovođa',
        };
    }

    /**
     * Administrators reach every company; bookkeepers only the assigned ones.
     */
    public function seesAllCompanies(): bool
    {
        return $this === self::Admin;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
