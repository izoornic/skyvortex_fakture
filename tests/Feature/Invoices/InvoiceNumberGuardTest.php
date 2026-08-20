<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\GenerateInvoiceNumber;
use App\Enums\DocumentType;
use App\Models\Company;
use RuntimeException;
use Tests\TestCase;

/**
 * Deliberately without RefreshDatabase: that trait wraps every test in a
 * transaction, which would make the guard unobservable. Nothing here touches
 * the database — the action refuses before it issues a query.
 */
class InvoiceNumberGuardTest extends TestCase
{
    public function test_a_number_is_never_handed_out_outside_a_transaction(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('transakcije');

        app(GenerateInvoiceNumber::class)->handle(
            new Company(['pib' => '100000001']),
            DocumentType::Invoice,
            2026,
        );
    }
}
