<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Records who changed what, and when. Storniranje is allowed to every role,
 * so the trail is the only thing carrying accountability.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->recordAudit('created', null, $model->auditableValues($model->getAttributes())));

        static::updated(function (Model $model) {
            $changed = $model->auditableValues($model->getChanges());

            if ($changed === []) {
                return;
            }

            $old = collect($model->getOriginal())
                ->only(array_keys($changed))
                ->all();

            $model->recordAudit('updated', $old, $changed);
        });

        static::deleted(fn (Model $model) => $model->recordAudit('deleted', $model->auditableValues($model->getOriginal()), null));
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * Attributes that must never reach the audit trail.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['created_at', 'updated_at', 'password', 'remember_token'];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        return collect($values)->except($this->auditExcluded())->all();
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function recordAudit(string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'company_id' => $this->auditCompanyId(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
        ]);
    }

    protected function auditCompanyId(): ?int
    {
        if ($this instanceof Company) {
            return $this->getKey();
        }

        return $this->company_id ?? null;
    }
}
