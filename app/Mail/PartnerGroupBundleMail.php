<?php

namespace App\Mail;

use App\Actions\PartnerGroups\CollectGroupInvoices;
use App\Actions\PartnerGroups\RenderPartnerGroupBundlePdf;
use App\Models\PartnerGroup;
use App\Support\PeriodLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One mail, one attachment, every invoice of the group for the period.
 */
class PartnerGroupBundleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PartnerGroup $group,
        public int $year,
        public ?int $month = null,
        public ?string $messageBody = null,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->group->company;

        return new Envelope(
            replyTo: $company->email ? [new Address($company->email, $company->name)] : [],
            subject: 'Fakture za '.PeriodLabel::for($this->year, $this->month)." — {$company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.partner-group-bundle',
            with: [
                'group' => $this->group,
                'company' => $this->group->company,
                'periodLabel' => PeriodLabel::for($this->year, $this->month),
                'invoices' => app(CollectGroupInvoices::class)
                    ->handle($this->group, $this->year, $this->month),
                'body' => $this->messageBody,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(RenderPartnerGroupBundlePdf::class);

        return [
            Attachment::fromData(
                fn () => $renderer->handle($this->group, $this->year, $this->month)->output(),
                $renderer->fileName($this->group, $this->year, $this->month),
            )->withMime('application/pdf'),
        ];
    }
}
