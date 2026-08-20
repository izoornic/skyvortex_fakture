<?php

namespace App\Mail;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $messageBody = null,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->invoice->company;

        return new Envelope(
            replyTo: $company->email ? [new Address($company->email, $company->name)] : [],
            subject: "{$this->invoice->type->label()} {$this->invoice->displayNumber()} — {$company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'company' => $this->invoice->company,
                'body' => $this->messageBody,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(RenderInvoicePdf::class);

        return [
            Attachment::fromData(
                fn () => $renderer->handle($this->invoice)->output(),
                $renderer->fileName($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
