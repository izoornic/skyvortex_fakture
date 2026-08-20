<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Poziv na broj, model 97.
 *
 * The control number is the ISO 7064 MOD 97-10 check pair: append "00" to the
 * reference, take the remainder modulo 97, and subtract it from 98. The same
 * formula IBAN uses.
 *
 * The remainder is computed digit by digit rather than through an integer cast,
 * because a reference easily exceeds what an int can hold.
 */
class PaymentReference
{
    public const MODEL = '97';

    /**
     * Two-digit control number for a reference made of digits.
     */
    public static function controlNumber(string $reference): string
    {
        $digits = self::digitsOnly($reference);

        if ($digits === '') {
            throw new InvalidArgumentException('Poziv na broj mora sadržati bar jednu cifru.');
        }

        $remainder = self::mod97($digits.'00');

        return str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Full reference with its control number, e.g. 2026-0001 becomes 47-20260001.
     */
    public static function forReference(string $reference): string
    {
        $digits = self::digitsOnly($reference);

        return self::controlNumber($digits).'-'.$digits;
    }

    /**
     * Reference for an invoice number such as 2026-0001 or AV-2026-0001:
     * letters and separators drop out, the digits carry the meaning.
     */
    public static function forInvoiceNumber(string $invoiceNumber): string
    {
        return self::forReference($invoiceNumber);
    }

    public static function isValid(string $reference): bool
    {
        if (! str_contains($reference, '-')) {
            return false;
        }

        [$control, $digits] = explode('-', $reference, 2);

        $digits = self::digitsOnly($digits);

        if ($digits === '' || ! preg_match('/^\d{2}$/', $control)) {
            return false;
        }

        return self::controlNumber($digits) === $control;
    }

    private static function digitsOnly(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    /**
     * Modulo 97 over an arbitrarily long numeric string.
     */
    private static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}
