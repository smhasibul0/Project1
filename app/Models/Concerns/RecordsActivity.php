<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Writes an activity log entry whenever the model is added, edited, has its status
 * updated, or is deleted — as whoever is signed in.
 *
 * A model can tune what is recorded with optional properties:
 *  - $activityIgnore: columns the app works out itself (totals, balances), so a
 *    recalculation is never taken for somebody's edit;
 *  - $activityStatusFields: columns whose change reads "Status updated" (default: status);
 *  - $activityParent: the relation to the record it belongs to (a payment's order),
 *    so that record's history shows it too.
 */
trait RecordsActivity
{
    /** Bookkeeping columns that never count as an edit. */
    private static array $activityAlwaysIgnored = ['id', 'created_at', 'updated_at', 'added_by', 'remember_token', 'email_verified_at'];

    /** Columns whose change is recorded but whose values never are. */
    private static array $activityRedacted = ['password'];

    /** @var array<class-string, array<string, string>> foreign key => relation, per class */
    private static array $activityForeignKeys = [];

    public static function bootRecordsActivity(): void
    {
        static::created(function (Model $model) {
            $model->logActivity(ActivityLog::ADDED);
        });

        static::updated(function (Model $model) {
            $changes = $model->activityChanges();

            if ($changes === []) {
                return;
            }

            $statusChanged = array_intersect(array_keys($changes), $model->activityStatusFields()) !== [];

            $model->logActivity($statusChanged ? ActivityLog::STATUS_UPDATED : ActivityLog::EDITED, null, $changes);
        });

        static::deleted(function (Model $model) {
            $model->logActivity(ActivityLog::DELETED);
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     */
    public function logActivity(string $action, ?string $description = null, array $changes = []): ?ActivityLog
    {
        return $this->shouldRecordActivity() ? ActivityLog::record($this, $action, $description, $changes) : null;
    }

    /**
     * Log which parts of a record a form changed — its items, its payments — when the
     * form saves them by clearing and re-creating the rows.
     *
     * @param  array<string, string>  $before  part => fingerprint
     * @param  array<string, string>  $after
     */
    public function logChangedContents(array $before, array $after): void
    {
        $changed = array_keys(array_diff_assoc($after, $before));

        if ($changed !== []) {
            $this->logActivity(ActivityLog::EDITED, 'Changed the '.Arr::join($changed, ', ', ' and '));
        }
    }

    /**
     * A fingerprint of related rows, for logChangedContents().
     *
     * @param  HasMany<Model, $this>  $rows
     */
    public static function activityFingerprint($rows): string
    {
        return md5($rows->orderBy('id')->get()
            ->map(fn (Model $row): array => Arr::except($row->getAttributes(), ['id', 'created_at', 'updated_at', 'added_by']))
            ->toJson());
    }

    /**
     * Whether this record's changes are logged at all — a model whose rows are only
     * ever the side effect of another logged change can say no.
     */
    public function shouldRecordActivity(): bool
    {
        return true;
    }

    /**
     * The record this one belongs to, if any.
     */
    public function activityParent(): ?Model
    {
        if (! property_exists($this, 'activityParent')) {
            return null;
        }

        $parent = $this->{$this->activityParent};

        return $parent instanceof Model ? $parent : null;
    }

    /**
     * How this record reads in the log: its number, name or title, then its amount,
     * then the record it belongs to ("৳5,000.00 — Order OR0001").
     */
    public function activityLabel(): string
    {
        $name = null;

        foreach (['order_no', 'quotation_no', 'lc_code', 'container_code', 'asset_code', 'loan_code', 'name', 'title', 'code', 'item_description'] as $attribute) {
            if (filled($this->getAttribute($attribute))) {
                $name = (string) $this->getAttribute($attribute);

                break;
            }
        }

        $amount = $this->getAttribute('amount');
        $parent = $this->activityParent();
        $parentType = $parent ? ActivityLog::typeKey($parent::class) : null;

        return implode(' — ', array_filter([
            $name,
            $amount !== null ? '৳'.number_format((float) $amount, 2) : null,
            $parent ? trim(($parentType ? ActivityLog::types()[$parentType]['label'] : '').' '.ActivityLog::labelFor($parent)) : null,
        ])) ?: '#'.$this->getKey();
    }

    /**
     * @return array<int, string>
     */
    public function activityStatusFields(): array
    {
        return property_exists($this, 'activityStatusFields') ? $this->activityStatusFields : ['status'];
    }

    /**
     * What this save changed, with each linked record named rather than numbered.
     *
     * @return array<string, array<string, mixed>>
     */
    public function activityChanges(): array
    {
        $ignored = array_merge(self::$activityAlwaysIgnored, property_exists($this, 'activityIgnore') ? $this->activityIgnore : []);
        $changes = [];

        foreach ($this->getChanges() as $field => $new) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            if (in_array($field, self::$activityRedacted, true)) {
                $changes[$field] = ['hidden' => true];

                continue;
            }

            $changes[$field] = [
                'old' => $this->activityValue($field, $this->getRawOriginal($field)),
                'new' => $this->activityValue($field, $new),
            ];
        }

        return $changes;
    }

    /**
     * A foreign key becomes the name of what it points at; anything else is kept as stored.
     */
    protected function activityValue(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (method_exists($this, 'activityDisplayValue') && ($display = $this->activityDisplayValue($field, $value)) !== null) {
            return $display;
        }

        $relation = $this->activityForeignKeyRelations()[$field] ?? null;

        if ($relation === null) {
            return $value;
        }

        $related = $this->{$relation}()->getRelated()->newQuery()->find($value);

        return $related ? ActivityLog::labelFor($related) : '#'.$value;
    }

    /**
     * Map each foreign key to its belongs-to relation, read once per class from the
     * relation methods' return types.
     *
     * @return array<string, string>
     */
    private function activityForeignKeyRelations(): array
    {
        if (isset(self::$activityForeignKeys[static::class])) {
            return self::$activityForeignKeys[static::class];
        }

        $map = [];

        foreach ((new \ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            if ($method->getNumberOfParameters() > 0
                || ! $type instanceof ReflectionNamedType
                || ! is_a($type->getName(), BelongsTo::class, true)
                || is_a($type->getName(), MorphTo::class, true)) {
                continue;
            }

            $map[$this->{$method->getName()}()->getForeignKeyName()] = $method->getName();
        }

        return self::$activityForeignKeys[static::class] = $map;
    }
}
