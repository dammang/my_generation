<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\RevisionAction;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Field-level revision capture.
 *
 * Genealogy data must be auditable: when a birth year moves from 1921 to 1923,
 * the old value, the new value, who changed it, when, why and on what evidence
 * all survive. Recording happens in a model observer rather than at call sites,
 * so it cannot be forgotten by whichever controller, action or Filament page
 * performed the write.
 *
 * Declare the audited fields on the model:
 *
 *     protected array $revisionable = ['birth_date', 'death_date', ...];
 *
 * Attach reason, source and change-request context before saving:
 *
 *     $person->withRevisionContext(reason: 'Baptism register, entry 114', sourceId: 331);
 */
trait RecordsRevisions
{
    protected ?string $revisionReason = null;

    protected ?int $revisionSourceId = null;

    protected ?int $revisionChangeRequestId = null;

    /** Bulk imports and rebuilds switch this off; see withoutRevisions(). */
    protected static bool $revisionsEnabled = true;

    protected static function bootRecordsRevisions(): void
    {
        static::created(function ($model): void {
            $model->writeRevision(RevisionAction::Created, null, null, $model->revisionSnapshot());
        });

        static::updated(function ($model): void {
            $model->writeFieldRevisions();
        });

        static::deleted(function ($model): void {
            // A force delete leaves nothing to attribute the revision to, and
            // the FK would be dangling; only soft deletes are recorded.
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            $model->writeRevision(RevisionAction::Deleted, null, $model->revisionSnapshot(), null);
        });

        // Only models using SoftDeletes fire this event.
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model): void {
                $model->writeRevision(RevisionAction::Restored, null, null, $model->revisionSnapshot());
            });
        }
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }

    /**
     * Run a callback with revision recording suspended. For imports, merges and
     * rebuilds that write their own, coarser audit record.
     */
    public static function withoutRevisions(callable $callback): mixed
    {
        $previous = static::$revisionsEnabled;
        static::$revisionsEnabled = false;

        try {
            return $callback();
        } finally {
            static::$revisionsEnabled = $previous;
        }
    }

    public function withRevisionContext(
        ?string $reason = null,
        ?int $sourceId = null,
        ?int $changeRequestId = null,
    ): static {
        $this->revisionReason = $reason;
        $this->revisionSourceId = $sourceId;
        $this->revisionChangeRequestId = $changeRequestId;

        return $this;
    }

    /** @return array<int, string> */
    public function revisionableFields(): array
    {
        return $this->revisionable ?? [];
    }

    /**
     * One row per changed audited field. Unaudited columns — counters, derived
     * years, cache flags — are skipped so the history stays readable.
     */
    protected function writeFieldRevisions(): void
    {
        $audited = $this->revisionableFields();

        foreach ($this->getChanges() as $field => $new) {
            if (! in_array($field, $audited, true)) {
                continue;
            }

            $old = $this->getOriginal($field);

            if ($this->revisionValue($old) === $this->revisionValue($new)) {
                continue;
            }

            $this->writeRevision(RevisionAction::Updated, $field, $old, $new);
        }
    }

    protected function writeRevision(
        RevisionAction $action,
        ?string $field,
        mixed $old,
        mixed $new,
    ): void {
        if (! static::$revisionsEnabled) {
            return;
        }

        Revision::create([
            'revisionable_type' => $this->getMorphClass(),
            'revisionable_id' => $this->getKey(),
            'field' => $field,
            'old_value' => $this->revisionValue($old),
            'new_value' => $this->revisionValue($new),
            'action' => $action,
            'reason' => $this->revisionReason,
            'source_id' => $this->revisionSourceId,
            'change_request_id' => $this->revisionChangeRequestId,
            'changed_by' => Auth::id(),
            'ip_hash' => $this->requestIpHash(),
        ]);
    }

    /** The audited fields only — a whole-record create or delete snapshot. */
    protected function revisionSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->revisionableFields() as $field) {
            $snapshot[$field] = $this->revisionValue($this->getAttribute($field));
        }

        return $snapshot;
    }

    /** Enums, dates and value objects all have to survive the JSON round trip. */
    protected function revisionValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \Stringable => (string) $value,
            default => $value,
        };
    }

    /** Hashed, never raw — an IP is personal data and we only need equality. */
    protected function requestIpHash(): ?string
    {
        $ip = request()?->ip();

        return $ip === null ? null : hash('sha256', $ip);
    }
}
