<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\RenderIpsQrCode;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\IpsQrPayload;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The NBS IPS QR code is scanned by a bank application, so the string inside it
 * is checked field by field — a code that looks fine and pays the wrong account
 * is worse than no code.
 */
class IpsQrCodeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private BankAccount $account;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->company = Company::factory()->create([
            'name' => 'SkyVortex d.o.o.',
            'short_name' => 'SkyVortex',
            'city' => 'Pančevo',
            'payment_code' => '221',
        ]);

        $this->account = BankAccount::factory()->primary()->create([
            'company_id' => $this->company->id,
            'account_number' => '160000000000000018',
        ]);

        $partner = Partner::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Čačanska privreda a.d.',
            'city' => 'Čačak',
        ]);

        $this->actingAs(User::factory()->admin()->create());
        app(CurrentCompany::class)->set($this->company);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'partner_id' => $partner->id,
            'bank_account_id' => $this->account->id,
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [[
            'name' => 'Održavanje', 'unit_code' => 'MON', 'unit_symbol' => 'mes',
            'quantity' => 1, 'unit_price' => 88300,
            'discount_percent' => 0, 'vat_rate' => 20, 'vat_category' => 'S',
        ]]);

        $this->invoice = app(IssueInvoice::class)->handle($invoice->refresh());
    }

    public function test_the_payload_carries_every_field_a_bank_needs(): void
    {
        $payload = $this->payload();
        $fields = $this->fields($payload);

        $this->assertSame('PR', $fields['K']);
        $this->assertSame('01', $fields['V']);
        $this->assertSame('1', $fields['C']);
        $this->assertSame('160000000000000018', $fields['R']);
        $this->assertSame('SkyVortex, Pančevo', $fields['N']);
        $this->assertSame('RSD105960,00', $fields['I']);
        $this->assertSame('Čačanska privreda a.d., Čačak', $fields['P']);
        $this->assertSame('221', $fields['SF']);
        $this->assertSame('Faktura 2026-0001', $fields['S']);
    }

    /**
     * The model sits against the reference with nothing between them.
     *
     * The stored reference is printed as `97 53-20260001`, where the hyphen only
     * separates the control number for a human eye. Model 97 does not allow it
     * in the field, and the National Bank's validator rejects the whole code
     * over it: "za ostale modele … ispravni su brojevi, slova i crtice".
     */
    public function test_the_reference_carries_no_separator(): void
    {
        $fields = $this->fields($this->payload());

        $this->assertStringStartsWith('97', $fields['RO']);
        $this->assertStringNotContainsString('-', $fields['RO']);
        $this->assertStringNotContainsString(' ', $fields['RO']);
        $this->assertSame('97'.str_replace('-', '', $this->invoice->payment_reference), $fields['RO']);
    }

    /**
     * The control number is the first two digits after the model, and it has to
     * satisfy the check the bank runs: reference with "00" appended, modulo 97,
     * subtracted from 98.
     */
    public function test_the_reference_passes_the_model_97_check(): void
    {
        $reference = substr($this->fields($this->payload())['RO'], 2);

        $control = substr($reference, 0, 2);
        $digits = substr($reference, 2);

        $remainder = 0;

        foreach (str_split($digits.'00') as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        $this->assertSame(
            str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT),
            $control,
            'Poziv na broj nije po modelu 97.',
        );
    }

    /**
     * The validator refuses Cyrillic in the name fields, and rejects the whole
     * code over one word of it.
     */
    public function test_cyrillic_names_are_written_in_latin(): void
    {
        $this->company->update(['short_name' => 'Жмурић', 'city' => 'Крагујевац']);

        $fields = $this->fields($this->payload());

        $this->assertSame('Žmurić, Kragujevac', $fields['N']);
        $this->assertDoesNotMatchRegularExpression('/\p{Cyrillic}/u', $this->payload());
    }

    /**
     * Only the alphabet changes: Čačak stays Čačak.
     */
    public function test_latin_diacritics_are_left_alone(): void
    {
        $fields = $this->fields($this->payload());

        $this->assertStringContainsString('Čačanska privreda', $fields['P']);
        $this->assertStringContainsString('Čačak', $fields['P']);
    }

    public function test_the_amount_matches_the_document_to_the_para(): void
    {
        $fields = $this->fields($this->payload());

        $this->assertSame('RSD'.IpsQrPayload::amount((float) $this->invoice->total), $fields['I']);
        $this->assertSame('105960.00', $this->invoice->total);
    }

    public function test_the_payload_stays_within_the_length_the_standard_allows(): void
    {
        $this->assertLessThanOrEqual(IpsQrPayload::MAX_LENGTH, mb_strlen($this->payload()));
    }

    /**
     * A pipe inside a name would split one field into two and shift everything
     * after it.
     */
    public function test_a_pipe_in_a_name_cannot_break_the_fields(): void
    {
        $this->company->update(['short_name' => 'Sky|Vortex', 'name' => 'Sky|Vortex d.o.o.']);

        $fields = $this->fields($this->payload());

        $this->assertSame('Sky Vortex, Pančevo', $fields['N']);
        $this->assertCount(10, $fields);
    }

    public function test_long_names_are_cut_to_the_seventy_characters_allowed(): void
    {
        $this->company->update([
            'short_name' => null,
            'name' => str_repeat('Preduzeće za proizvodnju i promet ', 4),
        ]);

        $fields = $this->fields($this->payload());

        $this->assertLessThanOrEqual(70, mb_strlen($fields['N']));
    }

    public function test_a_draft_has_nothing_to_pay_yet(): void
    {
        $draft = Invoice::factory()->forCompany($this->company)->create([
            'bank_account_id' => $this->account->id,
        ]);

        $this->assertNull(IpsQrPayload::for($draft, $this->account));
    }

    /**
     * IPS is domestic instant payment in dinars.
     */
    public function test_a_foreign_currency_document_gets_no_code(): void
    {
        $this->invoice->forceFill(['currency' => 'EUR', 'exchange_rate' => 117.2])->save();

        $this->assertNull(IpsQrPayload::for($this->invoice->refresh(), $this->account));
    }

    public function test_without_a_proper_account_there_is_no_code(): void
    {
        $this->account->update(['account_number' => '160-123']);

        $this->assertNull(IpsQrPayload::for($this->invoice, $this->account->refresh()));
        $this->assertNull(IpsQrPayload::for($this->invoice, null));
    }

    public function test_the_issuer_decides_the_payment_code(): void
    {
        $this->company->update(['payment_code' => '289']);

        $this->assertSame('289', $this->fields($this->payload())['SF']);
    }

    public function test_a_cancelled_document_still_shows_what_was_charged(): void
    {
        app(CancelInvoice::class)->handle($this->invoice, auth()->user(), 'Pogrešan iznos');

        // Storno keeps its number and its reference, so the code stays readable.
        $this->assertNotNull(IpsQrPayload::for($this->invoice->refresh(), $this->account));
    }

    public function test_the_code_is_drawn_as_a_png_and_lands_on_the_document(): void
    {
        $uri = app(RenderIpsQrCode::class)->handle($this->invoice, $this->account);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $this->assertSame("\x89PNG", substr($binary, 0, 4));

        $size = getimagesizefromstring($binary);
        $this->assertNotFalse($size);
        $this->assertSame($size[0], $size[1], 'QR kôd nije kvadrat.');

        $html = view('pdf.invoice', app(RenderInvoicePdf::class)->viewData($this->invoice))->render();

        $this->assertStringContainsString('NBS IPS QR', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_a_draft_prints_without_a_code(): void
    {
        $draft = Invoice::factory()->forCompany($this->company)->create([
            'bank_account_id' => $this->account->id,
        ]);

        $html = view('pdf.invoice', app(RenderInvoicePdf::class)->viewData($draft))->render();

        $this->assertStringNotContainsString('NBS IPS QR', $html);
    }

    private function payload(): string
    {
        $payload = IpsQrPayload::for($this->invoice->fresh(['company', 'partner']), $this->account);

        $this->assertNotNull($payload, 'Nije napravljen IPS QR sadržaj.');

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function fields(string $payload): array
    {
        return collect(explode('|', $payload))
            ->mapWithKeys(function (string $field) {
                [$tag, $value] = explode(':', $field, 2);

                return [$tag => $value];
            })
            ->all();
    }
}
