<?php

namespace Tests\Feature\Invoices;

use App\Support\PaymentReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PaymentReferenceTest extends TestCase
{
    public function test_the_control_number_satisfies_mod_97_10(): void
    {
        foreach (['20260001', '20269999', '1', '123456789012345678901234'] as $reference) {
            $control = PaymentReference::controlNumber($reference);

            $this->assertMatchesRegularExpression('/^\d{2}$/', $control);

            // ISO 7064 MOD 97-10: reference with "00" appended, plus the check
            // pair, is divisible by 97 with remainder 0 modulo the definition
            // control = 98 - (n mod 97).
            $remainder = $this->mod97($reference.'00');

            $this->assertSame(98 - $remainder, (int) $control);
            $this->assertGreaterThanOrEqual(1, (int) $control);
            $this->assertLessThanOrEqual(98, (int) $control);
        }
    }

    public function test_it_strips_everything_that_is_not_a_digit(): void
    {
        $this->assertSame(
            PaymentReference::controlNumber('20260001'),
            PaymentReference::controlNumber('AV-2026-0001'),
        );
    }

    public function test_a_reference_it_produced_validates(): void
    {
        foreach (['2026-0001', 'AV-2026-0042', 'KO-2026-1234'] as $number) {
            $reference = PaymentReference::forInvoiceNumber($number);

            $this->assertTrue(PaymentReference::isValid($reference), $number);
        }
    }

    public function test_a_wrong_control_number_does_not_validate(): void
    {
        $reference = PaymentReference::forInvoiceNumber('2026-0001');

        [$control, $digits] = explode('-', $reference, 2);

        $wrongControl = str_pad((string) (((int) $control + 1) % 99), 2, '0', STR_PAD_LEFT);

        $this->assertFalse(PaymentReference::isValid("{$wrongControl}-{$digits}"));
    }

    /**
     * MOD 97-10 catches every single-digit substitution, which is the mistake
     * people actually make when copying a reference off an invoice.
     *
     * It does not catch everything: the check is two digits wide, so roughly
     * one alteration in 97 lands on the same control number. Changing the
     * length of the reference is one such case — 20260001 and 202600019 share
     * a control number. The scheme is a typo guard, not a signature.
     */
    public function test_every_single_digit_substitution_is_caught(): void
    {
        $digits = '20260001';
        $control = PaymentReference::controlNumber($digits);

        foreach (str_split($digits) as $position => $original) {
            for ($replacement = 0; $replacement <= 9; $replacement++) {
                if ((string) $replacement === $original) {
                    continue;
                }

                $tampered = substr_replace($digits, (string) $replacement, $position, 1);

                $this->assertFalse(
                    PaymentReference::isValid("{$control}-{$tampered}"),
                    "Izmena cifre na poziciji {$position} u {$replacement} nije uhvaćena.",
                );
            }
        }
    }

    public function test_it_handles_references_longer_than_an_integer(): void
    {
        $long = str_repeat('9', 40);

        $this->assertMatchesRegularExpression('/^\d{2}$/', PaymentReference::controlNumber($long));
    }

    public function test_a_reference_without_digits_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PaymentReference::controlNumber('AV-');
    }

    private function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }
}
