<?php

namespace Tests\Feature\Invoices;

use App\Actions\Companies\StoreCompanyLogo;
use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\SaveInvoiceItems;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\InvoiceTotals;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $company = Company::factory()->create([
            'name' => 'Đurđević & Šišković d.o.o.',
            'city' => 'Niš',
        ]);
        BankAccount::factory()->primary()->create([
            'company_id' => $company->id,
            'account_number' => '160000000000000018',
        ]);

        $partner = Partner::factory()->create([
            'company_id' => $company->id,
            'name' => 'Čačanska privreda a.d.',
        ]);

        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        app(CurrentCompany::class)->set($company);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'bank_account_id' => $company->bankAccounts()->first()->id,
            'issue_date' => '2026-03-05',
            'note' => 'Hvala na saradnji.',
        ]);

        app(SaveInvoiceItems::class)->handle($invoice, [
            [
                'name' => 'Održavanje aplikacije',
                'description' => 'mart 2026',
                'unit_code' => 'MON', 'unit_symbol' => 'mes',
                'quantity' => 1, 'unit_price' => 85000,
                'discount_percent' => 0, 'vat_rate' => 20, 'vat_category' => 'S',
            ],
            [
                'name' => 'Licenca',
                'unit_code' => 'H87', 'unit_symbol' => 'kom',
                'quantity' => 3, 'unit_price' => 1200,
                'discount_percent' => 0, 'vat_rate' => 10, 'vat_category' => 'S',
            ],
        ]);

        $this->invoice = app(IssueInvoice::class)->handle($invoice->refresh());
    }

    public function test_the_template_carries_the_document_onto_paper(): void
    {
        $html = $this->renderTemplate();

        // Serbian diacritics must survive into the markup dompdf consumes.
        $this->assertStringContainsString('Đurđević &amp; Šišković d.o.o.', $html);
        $this->assertStringContainsString('Čačanska privreda a.d.', $html);
        $this->assertStringContainsString('Održavanje aplikacije', $html);

        $this->assertStringContainsString('2026-0001', $html);
        $this->assertStringContainsString($this->invoice->fullPaymentReference(), $html);
        $this->assertStringContainsString('160-0000000000000-18', $html);
        $this->assertStringContainsString('05.03.2026.', $html);
        $this->assertStringContainsString('Hvala na saradnji.', $html);
    }

    public function test_the_totals_on_paper_match_the_stored_ones(): void
    {
        $html = $this->renderTemplate();

        // 85000 + 3600 base, VAT 17000 + 360.
        $this->assertSame('88600.00', $this->invoice->subtotal);
        $this->assertSame('17360.00', $this->invoice->vat_total);
        $this->assertSame('105960.00', $this->invoice->total);

        $this->assertStringContainsString('105.960,00', $html);
        $this->assertStringContainsString('88.600,00', $html);
    }

    public function test_the_recapitulation_splits_by_rate(): void
    {
        $recapitulation = InvoiceTotals::recapitulation($this->invoice);

        $this->assertCount(2, $recapitulation);
        $this->assertSame(20.0, $recapitulation[0]['vat_rate']);
        $this->assertSame(85000.00, $recapitulation[0]['base']);
        $this->assertSame(17000.00, $recapitulation[0]['vat']);
        $this->assertSame(10.0, $recapitulation[1]['vat_rate']);
        $this->assertSame(360.00, $recapitulation[1]['vat']);

        $this->assertSame(
            (float) $this->invoice->vat_total,
            round($recapitulation->sum('vat'), 2),
        );
    }

    public function test_a_cancelled_document_says_so_on_paper(): void
    {
        app(CancelInvoice::class)
            ->handle($this->invoice, auth()->user(), 'Pogrešan iznos');

        $html = $this->renderTemplate();

        $this->assertStringContainsString('STORNIRANO', $html);
        $this->assertStringContainsString('Pogrešan iznos', $html);
    }

    public function test_dompdf_produces_a_file_with_the_fonts_embedded(): void
    {
        $output = app(RenderInvoicePdf::class)
            ->handle($this->invoice)
            ->output();

        $this->assertStringStartsWith('%PDF', $output);

        // Without an embedded unicode font, č/ć/š/ž/đ come out as blanks.
        $this->assertStringContainsString('DejaVuSans', $output);
    }

    /**
     * dompdf is not allowed to fetch anything, so the logo has to be carried
     * inside the document — and an SVG has to come out as vectors, not as a
     * hole where the picture should be.
     */
    public function test_the_company_logo_is_printed_on_the_document(): void
    {
        Storage::fake(Company::LOGO_DISK);

        $company = $this->invoice->company;
        $path = Company::LOGO_DIRECTORY."/{$company->id}/logo.svg";

        Storage::disk(Company::LOGO_DISK)->put($path, '<svg xmlns="http://www.w3.org/2000/svg" '
            .'viewBox="0 0 200 60" width="200" height="60"><rect width="200" height="60" fill="#0f766e"/></svg>');

        $company->update(['logo_path' => $path]);

        $this->assertStringContainsString('data:image/svg+xml;base64,', $this->renderTemplate());

        $output = app(RenderInvoicePdf::class)->handle($this->invoice->fresh())->output();

        $this->assertStringStartsWith('%PDF', $output);
        $this->assertStringContainsString(
            '0.059 0.463 0.431 rg',
            $this->contentStreams($output),
            'Logo nije iscrtan u PDF-u — dompdf nije razumeo SVG.',
        );
    }

    /**
     * The size of the image box says nothing: nothing clips an SVG to it. A logo
     * whose box measured a tidy 42px covered the whole sheet, because php-svg-lib
     * scaled its `<symbol>` tiles by the root viewport — the ink ran 6440pt wide
     * on a 595pt page while every number in the placement matrix looked right.
     *
     * So the assertion is on the ink: where the drawing commands actually land.
     */
    #[DataProvider('logoShapes')]
    public function test_no_logo_can_run_off_the_page(string $shape, string $svg): void
    {
        Storage::fake(Company::LOGO_DISK);

        // Through the real pipeline: what the PDF gets is what an upload stores.
        app(StoreCompanyLogo::class)->handle(
            $this->invoice->company,
            UploadedFile::fake()->createWithContent('logo.svg', $svg),
        );

        $output = app(RenderInvoicePdf::class)->handle($this->invoice->fresh())->output();
        [$width, $height] = $this->logoInkSize($output);

        $this->assertGreaterThan(0.0, $width, "Logo ({$shape}) uopšte nije iscrtan.");

        // 180px x 42px, in points.
        $this->assertLessThanOrEqual(135.0, round($width, 1), "Logo ({$shape}) je širi od zaglavlja.");
        $this->assertLessThanOrEqual(31.5, round($height, 1), "Logo ({$shape}) je viši od zaglavlja.");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function logoShapes(): array
    {
        $box = fn (float $width, float $height): string => sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$s %2$s">'
            .'<rect width="%1$s" height="%2$s" fill="#0f766e"/></svg>',
            $width,
            $height,
        );

        // The shape that broke: tiny symbol, many instances, no width/height on
        // the root — an Illustrator export made of repeated tiles.
        $tiles = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 200 50">'
            .'<defs><symbol id="tile" viewBox="0 0 1.09 1.09"><rect width="1.09" height="1.09" fill="#0f766e"/></symbol></defs>';

        for ($i = 0; $i < 40; $i++) {
            $tiles .= sprintf(
                '<use width="1.09" height="1.09" transform="translate(%d %d) scale(4)" xlink:href="#tile"/>',
                $i * 5,
                $i % 2 * 20,
            );
        }

        return [
            'širok' => ['širok', $box(3000.0, 100.0)],
            'visok' => ['visok', $box(2000.0, 1500.0)],
            'kvadrat' => ['kvadrat', $box(500.0, 500.0)],
            'stvarnih razmera' => ['stvarnih razmera', $box(238.87, 88.55)],
            'od simbola' => ['od simbola', $tiles.'</svg>'],
        ];
    }

    /**
     * Replays the page's drawing commands through the transform stack and returns
     * how much of the paper the logo's ink covers, in points.
     *
     * @return array{0: float, 1: float}
     */
    private function logoInkSize(string $pdf): array
    {
        $tokens = preg_split('/\s+/', trim($this->contentStreams($pdf))) ?: [];

        $matrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $stack = [];
        $arguments = [];
        $logoDepth = null;
        $left = $bottom = INF;
        $right = $top = -INF;

        $compose = fn (array $a, array $b): array => [
            $a[0] * $b[0] + $a[1] * $b[2], $a[0] * $b[1] + $a[1] * $b[3],
            $a[2] * $b[0] + $a[3] * $b[2], $a[2] * $b[1] + $a[3] * $b[3],
            $a[4] * $b[0] + $a[5] * $b[2] + $b[4], $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
        ];

        $plot = function (float $x, float $y) use (&$matrix, &$left, &$bottom, &$right, &$top): void {
            $pageX = $matrix[0] * $x + $matrix[2] * $y + $matrix[4];
            $pageY = $matrix[1] * $x + $matrix[3] * $y + $matrix[5];

            $left = min($left, $pageX);
            $right = max($right, $pageX);
            $bottom = min($bottom, $pageY);
            $top = max($top, $pageY);
        };

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $arguments[] = (float) $token;

                continue;
            }

            switch ($token) {
                case 'q':
                    $stack[] = $matrix;
                    break;

                case 'Q':
                    $matrix = array_pop($stack) ?? $matrix;

                    if ($logoDepth !== null && count($stack) < $logoDepth) {
                        $logoDepth = null;
                    }
                    break;

                case 'cm':
                    if (count($arguments) >= 6) {
                        $applied = array_slice($arguments, -6);
                        $matrix = $compose($applied, $matrix);

                        // The image placement: the first uniform scale that is not 1.
                        if ($logoDepth === null && $applied[1] === 0.0 && $applied[2] === 0.0
                            && abs($applied[0] - $applied[3]) < 1e-9 && abs($applied[0] - 1.0) > 1e-9) {
                            $logoDepth = count($stack);
                        }
                    }
                    break;

                case 'm':
                case 'l':
                    if ($logoDepth !== null && count($arguments) >= 2) {
                        [$x, $y] = array_slice($arguments, -2);
                        $plot($x, $y);
                    }
                    break;

                case 'c':
                    if ($logoDepth !== null && count($arguments) >= 6) {
                        $points = array_slice($arguments, -6);
                        $plot($points[0], $points[1]);
                        $plot($points[2], $points[3]);
                        $plot($points[4], $points[5]);
                    }
                    break;

                case 're':
                    if ($logoDepth !== null && count($arguments) >= 4) {
                        [$x, $y, $width, $height] = array_slice($arguments, -4);
                        $plot($x, $y);
                        $plot($x + $width, $y + $height);
                    }
                    break;
            }

            $arguments = [];
        }

        return $right === -INF ? [0.0, 0.0] : [$right - $left, $top - $bottom];
    }

    public function test_a_company_without_a_logo_prints_as_before(): void
    {
        $html = $this->renderTemplate();

        // Not "no data: image at all" — the IPS QR code is one too.
        $this->assertStringNotContainsString('class="logo"', $html);
        $this->assertStringContainsString('Đurđević &amp; Šišković d.o.o.', $html);
    }

    /**
     * Everything dompdf drew, as drawing commands.
     */
    private function contentStreams(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        return collect($matches[1])
            ->map(fn (string $stream): string => @gzuncompress(ltrim($stream, "\r\n")) ?: $stream)
            ->implode("\n");
    }

    private function renderTemplate(): string
    {
        $invoice = $this->invoice->fresh(['company.primaryBankAccount', 'partner', 'items', 'bankAccount']);

        return view('pdf.invoice', app(RenderInvoicePdf::class)->viewData($invoice))->render();
    }
}
