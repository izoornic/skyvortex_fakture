<?php

namespace App\Actions\Invoices;

use App\Models\BankAccount;
use App\Models\Invoice;
use App\Support\IpsQrPayload;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Draws the NBS IPS QR code for a document.
 *
 * PNG rather than SVG: the code goes into a PDF, and dompdf draws a raster image
 * exactly as given, while its SVG renderer has opinions of its own.
 *
 * Error correction is set to Medium — a payment code is scanned off paper that
 * gets folded and stamped, and the extra redundancy costs a few millimetres.
 */
class RenderIpsQrCode
{
    private const SIZE = 320;

    public function handle(Invoice $invoice, ?BankAccount $account): ?string
    {
        $payload = IpsQrPayload::for($invoice, $account);

        if ($payload === null) {
            return null;
        }

        return $this->dataUri($payload);
    }

    public function dataUri(string $payload): string
    {
        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::SIZE,
            margin: 0,
        );

        return (new PngWriter)->write($qrCode)->getDataUri();
    }
}
