<?php

namespace App\Support;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;

/**
 * The text an NBS IPS QR code carries.
 *
 * Tagged fields joined by a pipe, as the National Bank prescribes:
 * `K:PR|V:01|C:1|R:{račun}|N:{primalac}|I:RSD{iznos}|P:{platilac}|SF:{šifra}|S:{svrha}|RO:{model+poziv}`
 *
 * Two details decide whether a bank accepts the code: the amount is written with
 * a comma and no thousands separator, and the model sits against the reference
 * with nothing between them.
 */
class IpsQrPayload
{
    /** The code stands for a printed payment request. */
    private const IDENTIFIER = 'PR';

    private const VERSION = '01';

    /** 1 means UTF-8, which is what our names need. */
    private const CHARACTER_SET = '1';

    /**
     * Field caps from the specification. They are enforced here rather than
     * trusted, because a name three characters too long is refused as a whole.
     */
    private const MAX_NAME = 70;

    private const MAX_PURPOSE = 35;

    private const MAX_REFERENCE = 35;

    /**
     * The whole string has to stay comfortably scannable from paper.
     */
    public const MAX_LENGTH = 331;

    /**
     * Whether this document can carry a code at all.
     *
     * IPS is domestic instant payment in dinars, and a payment without a number
     * and a reference cannot be matched to anything — so a draft never gets one.
     */
    public static function isAvailableFor(Invoice $invoice, ?BankAccount $account): bool
    {
        return $invoice->currency === 'RSD'
            && $invoice->status->isIssued()
            && $invoice->payment_reference !== null
            && (float) $invoice->total > 0
            && self::accountDigits($account) !== null;
    }

    public static function for(Invoice $invoice, ?BankAccount $account): ?string
    {
        if (! self::isAvailableFor($invoice, $account)) {
            return null;
        }

        $company = $invoice->company;

        $fields = [
            'K' => self::IDENTIFIER,
            'V' => self::VERSION,
            'C' => self::CHARACTER_SET,
            'R' => self::accountDigits($account),
            'N' => self::clean($company->displayName().', '.$company->city, self::MAX_NAME),
            'I' => 'RSD'.self::amount((float) $invoice->total),
            'P' => self::clean($invoice->partner->name.', '.$invoice->partner->city, self::MAX_NAME),
            'SF' => self::paymentCode($company->payment_code),
            'S' => self::clean($invoice->type->label().' '.$invoice->number, self::MAX_PURPOSE),
            'RO' => self::reference($invoice->payment_reference_model, $invoice->payment_reference),
        ];

        return collect($fields)
            ->reject(fn (?string $value) => $value === null || $value === '')
            ->map(fn (string $value, string $tag) => $tag.':'.$value)
            ->implode('|');
    }

    /**
     * Comma for the decimal point, nothing between the thousands: `RSD105960,00`.
     */
    public static function amount(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    /**
     * Model and reference, with nothing between them.
     *
     * The reference is printed as `97 53-20260017`, where the hyphen only
     * separates the control number from the rest for a human eye. Model 97 does
     * not allow it in the field itself — the National Bank's validator rejects
     * the whole code over it — so the separator is dropped here and nowhere else.
     */
    private static function reference(?string $model, ?string $reference): string
    {
        $model = preg_replace('/\D/', '', (string) $model) ?: '00';
        $reference = preg_replace('/[^A-Za-z0-9]/', '', (string) $reference) ?? '';

        return mb_substr($model.$reference, 0, self::MAX_REFERENCE);
    }

    /**
     * Eighteen digits, no dashes. Anything else is not an account we can put in
     * front of a bank.
     */
    private static function accountDigits(?BankAccount $account): ?string
    {
        if ($account === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $account->account_number) ?? '';

        return mb_strlen($digits) === 18 ? $digits : null;
    }

    private static function paymentCode(?string $code): string
    {
        $digits = preg_replace('/\D/', '', (string) $code) ?? '';

        return mb_strlen($digits) === 3 ? $digits : Company::DEFAULT_PAYMENT_CODE;
    }

    /**
     * A pipe inside a value would split the field in two, and a line break would
     * end it early — both come out. Cyrillic goes to Latin on the way.
     */
    private static function clean(string $value, int $limit): string
    {
        $value = self::latin($value);
        $value = str_replace('|', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_substr(trim($value), 0, $limit);
    }

    /**
     * Serbian Cyrillic written in Latin.
     *
     * The National Bank's validator refuses Cyrillic in the name fields — the
     * code is rejected whole over one word of it. Latin diacritics are fine, so
     * Čačak stays Čačak and only the alphabet changes.
     */
    public static function latin(string $value): string
    {
        return strtr($value, [
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Ђ' => 'Đ',
            'Е' => 'E', 'Ж' => 'Ž', 'З' => 'Z', 'И' => 'I', 'Ј' => 'J', 'К' => 'K',
            'Л' => 'L', 'Љ' => 'Lj', 'М' => 'M', 'Н' => 'N', 'Њ' => 'Nj', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'Ћ' => 'Ć', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Č', 'Џ' => 'Dž', 'Ш' => 'Š',

            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'ђ' => 'đ',
            'е' => 'e', 'ж' => 'ž', 'з' => 'z', 'и' => 'i', 'ј' => 'j', 'к' => 'k',
            'л' => 'l', 'љ' => 'lj', 'м' => 'm', 'н' => 'n', 'њ' => 'nj', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'ћ' => 'ć', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'č', 'џ' => 'dž', 'ш' => 'š',
        ]);
    }
}
