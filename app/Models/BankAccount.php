<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'bank_name',
        'account_number',
        'iban',
        'swift',
        'currency',
        'is_primary',
        'sort_order',
    ];

    protected $attributes = [
        'currency' => 'RSD',
        'is_primary' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Serbian account numbers are written 000-0000000000000-00.
     */
    public function formattedAccountNumber(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->account_number);

        if (mb_strlen((string) $digits) !== 18) {
            return (string) $this->account_number;
        }

        return mb_substr($digits, 0, 3).'-'.mb_substr($digits, 3, 13).'-'.mb_substr($digits, 16, 2);
    }
}
