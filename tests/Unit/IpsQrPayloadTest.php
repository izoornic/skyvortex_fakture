<?php

namespace Tests\Unit;

use App\Support\IpsQrPayload;
use PHPUnit\Framework\TestCase;

/**
 * The two details a bank refuses a code over: the amount format and the shape of
 * the reference. Both are checked here without touching the database.
 */
class IpsQrPayloadTest extends TestCase
{
    public function test_the_amount_uses_a_comma_and_no_thousands_separator(): void
    {
        $this->assertSame('105960,00', IpsQrPayload::amount(105960.00));
        $this->assertSame('3596,13', IpsQrPayload::amount(3596.13));
        $this->assertSame('0,01', IpsQrPayload::amount(0.01));
        $this->assertSame('1234567,89', IpsQrPayload::amount(1234567.89));
    }

    public function test_the_amount_always_carries_two_decimals(): void
    {
        $this->assertSame('1500,50', IpsQrPayload::amount(1500.5));
        $this->assertSame('90,00', IpsQrPayload::amount(90.0));
    }

    /**
     * The National Bank's validator refuses Cyrillic in the name fields and
     * rejects the whole code over one word of it.
     */
    public function test_serbian_cyrillic_is_written_in_latin(): void
    {
        $this->assertSame('Kragujevac', IpsQrPayload::latin('Крагујевац'));
        $this->assertSame('Žmurić i Jević', IpsQrPayload::latin('Жмурић и Јевић'));
        $this->assertSame('Đorđe', IpsQrPayload::latin('Ђорђе'));
    }

    /**
     * The three letters written with two in Latin.
     */
    public function test_the_digraphs_come_out_whole(): void
    {
        $this->assertSame('Ljubljana', IpsQrPayload::latin('Љубљана'));
        $this->assertSame('Njegoš', IpsQrPayload::latin('Његош'));
        $this->assertSame('Džak', IpsQrPayload::latin('Џак'));
    }

    /**
     * Only the alphabet changes — a name already in Latin is left as it is,
     * diacritics and all.
     */
    public function test_latin_text_passes_through_untouched(): void
    {
        $this->assertSame('Čačanska privreda a.d.', IpsQrPayload::latin('Čačanska privreda a.d.'));
        $this->assertSame('Niš, Srbija', IpsQrPayload::latin('Niš, Srbija'));
        $this->assertSame('Digital Skyvortex', IpsQrPayload::latin('Digital Skyvortex'));
    }
}
