<?php

namespace App\Actions\Invoices;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands out the next document number.
 *
 * The sequence row is locked for the duration of the transaction, so two
 * documents issued at the same moment cannot receive the same number.
 *
 * Must be called inside an open transaction; a lock outside one buys nothing.
 */
class GenerateInvoiceNumber
{
    /**
     * @return array{number: string, year: int, sequence: int}
     */
    public function handle(Company $company, DocumentType $type, int $year): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Broj dokumenta se dodeljuje samo unutar transakcije.');
        }

        $sequence = InvoiceNumberSequence::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = InvoiceNumberSequence::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $company->id,
                'type' => $type,
                'year' => $year,
                'last_number' => 0,
            ]);

            // Re-read under a lock: another request may have created it first.
            $sequence = InvoiceNumberSequence::withoutGlobalScope(CompanyScope::class)
                ->whereKey($sequence->getKey())
                ->lockForUpdate()
                ->first();
        }

        $next = $sequence->last_number + 1;

        $sequence->update(['last_number' => $next]);

        return [
            'number' => $this->format($type, $year, $next),
            'year' => $year,
            'sequence' => $next,
        ];
    }

    public function format(DocumentType $type, int $year, int $sequence): string
    {
        return str_replace(
            ['{godina}', '{broj}'],
            [$year, str_pad((string) $sequence, 4, '0', STR_PAD_LEFT)],
            $type->numberTemplate(),
        );
    }
}
