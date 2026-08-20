<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canAccessCompany($invoice->company);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only a draft is editable; an issued document is corrected, not changed.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isEditable() && $user->canAccessCompany($invoice->company);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    /**
     * Storniranje is open to every role, without approval — the audit trail
     * carries the accountability.
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $invoice->status->isIssued()
            && $invoice->status->value !== 'stornirana'
            && $user->canAccessCompany($invoice->company);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $invoice->status->isIssued() && $user->canAccessCompany($invoice->company);
    }
}
