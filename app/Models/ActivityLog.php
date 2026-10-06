<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * One thing somebody did: added, edited, updated the status of, or deleted a record.
 *
 * Rows are written by the RecordsActivity trait on every model save, and by hand for
 * the few writes that go round the model (a tariff import, a role's permission matrix,
 * an order loaded into a container).
 */
class ActivityLog extends Model
{
    public const ADDED = 'added';

    public const EDITED = 'edited';

    public const STATUS_UPDATED = 'status_updated';

    public const DELETED = 'deleted';

    public const IMPORTED = 'imported';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_changes' => 'array',
        ];
    }

    /**
     * Every kind of record the log covers: the key used in links and filters, what
     * to call it, and the page that shows one.
     *
     * @return array<string, array{class: class-string<Model>, label: string, route: string|null}>
     */
    public static function types(): array
    {
        return [
            'order' => ['class' => Order::class, 'label' => 'Order', 'route' => 'order.show'],
            'order_payment' => ['class' => OrderPayment::class, 'label' => 'Order Payment', 'route' => null],
            'order_cost' => ['class' => OrderCost::class, 'label' => 'Order Cost', 'route' => null],
            'order_scan' => ['class' => OrderScan::class, 'label' => 'Carton Count', 'route' => null],
            'quotation' => ['class' => Quotation::class, 'label' => 'Quotation', 'route' => 'quotation.show'],
            'container' => ['class' => Container::class, 'label' => 'Container', 'route' => 'container.show'],
            'container_cost' => ['class' => ContainerCost::class, 'label' => 'Container Cost', 'route' => null],
            'container_document' => ['class' => ContainerDocument::class, 'label' => 'Container Document', 'route' => null],
            'lc' => ['class' => Lc::class, 'label' => 'LC', 'route' => 'lc.show'],
            'lc_cost' => ['class' => LcCost::class, 'label' => 'LC Cost', 'route' => null],
            'customer' => ['class' => Contact::class, 'label' => 'Customer', 'route' => null],
            'customer_group' => ['class' => CustomerGroup::class, 'label' => 'Customer Group', 'route' => null],
            'hs_code' => ['class' => HsCode::class, 'label' => 'HS Code', 'route' => null],
            'valuation_rate' => ['class' => ValuationRate::class, 'label' => 'Rate', 'route' => 'rates.show'],
            'exchange_rate' => ['class' => ExchangeRate::class, 'label' => 'Exchange Rate', 'route' => null],
            'transportation_mode' => ['class' => TransportationMode::class, 'label' => 'Transportation Mode', 'route' => null],
            'packing_type' => ['class' => PackingType::class, 'label' => 'Packing Type', 'route' => null],
            'cost_category' => ['class' => CostCategory::class, 'label' => 'Cost Category', 'route' => null],
            'payment_account' => ['class' => PaymentAccount::class, 'label' => 'Payment Account', 'route' => 'payment.account.book'],
            'account_type' => ['class' => AccountType::class, 'label' => 'Account Type', 'route' => null],
            'transaction' => ['class' => Transaction::class, 'label' => 'Account Entry', 'route' => null],
            'office_cost_type' => ['class' => OfficeCostType::class, 'label' => 'Office Cost Type', 'route' => null],
            'office_expense' => ['class' => OfficeExpense::class, 'label' => 'Office Expense', 'route' => null],
            'office_expense_payment' => ['class' => OfficeExpensePayment::class, 'label' => 'Office Expense Payment', 'route' => null],
            'loan' => ['class' => Loan::class, 'label' => 'Loan', 'route' => null],
            'loan_payment' => ['class' => LoanPayment::class, 'label' => 'Loan Payment', 'route' => null],
            'asset' => ['class' => Asset::class, 'label' => 'Asset', 'route' => null],
            'asset_category' => ['class' => AssetCategory::class, 'label' => 'Asset Category', 'route' => null],
            'asset_depreciation' => ['class' => AssetDepreciation::class, 'label' => 'Depreciation', 'route' => null],
            'warehouse' => ['class' => Warehouse::class, 'label' => 'Warehouse', 'route' => null],
            'warehouse_stock' => ['class' => WarehouseStock::class, 'label' => 'Stock Lot', 'route' => null],
            'stock_movement' => ['class' => WarehouseStockMovement::class, 'label' => 'Stock Movement', 'route' => null],
            'expense_category' => ['class' => ExpenseCategory::class, 'label' => 'Expense Category', 'route' => null],
            'warehouse_expense' => ['class' => WarehouseExpense::class, 'label' => 'Warehouse Expense', 'route' => null],
            'warehouse_expense_payment' => ['class' => WarehouseExpensePayment::class, 'label' => 'Warehouse Expense Payment', 'route' => null],
            'warehouse_staff' => ['class' => WarehouseStaff::class, 'label' => 'Warehouse Staff', 'route' => null],
            'staff_document' => ['class' => WarehouseStaffDocument::class, 'label' => 'Staff Document', 'route' => null],
            'salary_payment' => ['class' => StaffSalaryPayment::class, 'label' => 'Salary Payment', 'route' => null],
            'user' => ['class' => User::class, 'label' => 'User', 'route' => null],
            'role' => ['class' => Role::class, 'label' => 'Role', 'route' => null],
            'company_setting' => ['class' => CompanySetting::class, 'label' => 'Company Settings', 'route' => null],
        ];
    }

    /**
     * The link and filter key for a model class ("order" for App\Models\Order).
     */
    public static function typeKey(string $class): ?string
    {
        foreach (static::types() as $key => $type) {
            if ($type['class'] === $class) {
                return $key;
            }
        }

        return null;
    }

    /** Set while a form rebuilds rows it has just cleared, so they aren't logged as new. */
    private static bool $paused = false;

    /**
     * Run a write without logging it — for a form that clears and re-creates its
     * line items, which would otherwise read as everything being added again.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutRecording(callable $callback): mixed
    {
        $wasPaused = static::$paused;
        static::$paused = true;

        try {
            return $callback();
        } finally {
            static::$paused = $wasPaused;
        }
    }

    /**
     * Write one entry, as the signed-in user.
     *
     * One save often writes the same record twice (the details, then the totals worked
     * out from them), so within a request a later edit folds into the entry already
     * written for that record — and anything done while adding it is part of adding it.
     *
     * @param  array<string, array<string, mixed>>  $changes  field => ['old' => …, 'new' => …]
     * @param  Model|null  $parent  the record whose history should also show this, when it isn't the subject's own parent
     */
    public static function record(Model $subject, string $action, ?string $description = null, array $changes = [], ?Model $parent = null): ?self
    {
        if (static::$paused) {
            return null;
        }

        $key = $subject->getMorphClass().'#'.$subject->getKey();
        $written = request()->attributes->get('activity.written', []);
        $earlier = $written[$key] ?? null;

        // A long-running process (a queue worker) keeps one request; don't fold across it.
        if ($earlier && $earlier->created_at?->lt(now()->subMinute())) {
            $earlier = null;
        }

        if ($earlier && in_array($action, [self::EDITED, self::STATUS_UPDATED], true)) {
            if ($earlier->action === self::ADDED) {
                return $earlier;
            }

            if ($earlier->action !== self::DELETED) {
                return tap($earlier)->update([
                    'action' => $earlier->action === self::STATUS_UPDATED ? self::STATUS_UPDATED : $action,
                    'subject_label' => Str::limit(static::labelFor($subject), 250),
                    'description' => static::joinDescriptions($earlier->description, $description),
                    'field_changes' => static::mergeChanges($earlier->field_changes ?? [], $changes) ?: null,
                ]);
            }
        }

        $user = Auth::user();
        $parent ??= method_exists($subject, 'activityParent') ? $subject->activityParent() : null;

        $entry = static::create([
            'user_id' => $user?->getKey(),
            'user_name' => $user ? static::userName($user) : null,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_label' => Str::limit(static::labelFor($subject), 250),
            'parent_type' => $parent?->getMorphClass(),
            'parent_id' => $parent?->getKey(),
            'description' => $description === null ? null : Str::limit($description, 250),
            'field_changes' => $changes === [] ? null : $changes,
        ]);

        $written[$key] = $entry;
        request()->attributes->set('activity.written', $written);

        return $entry;
    }

    /**
     * One entry for a change made to many records at once — a tariff import — which
     * has no single record to hang it on.
     *
     * @param  class-string<Model>  $subjectClass
     */
    public static function recordBulk(string $subjectClass, string $action, string $label, ?string $description = null): ?self
    {
        if (static::$paused) {
            return null;
        }

        $user = Auth::user();

        return static::create([
            'user_id' => $user?->getKey(),
            'user_name' => $user ? static::userName($user) : null,
            'action' => $action,
            'subject_type' => (new $subjectClass)->getMorphClass(),
            'subject_label' => Str::limit($label, 250),
            'description' => $description === null ? null : Str::limit($description, 250),
        ]);
    }

    private static function joinDescriptions(?string $earlier, ?string $later): ?string
    {
        $parts = array_values(array_unique(array_filter([$earlier, $later], fn (?string $part): bool => filled($part))));

        return $parts === [] ? null : Str::limit(implode('; ', $parts), 250);
    }

    /**
     * A field changed twice in one save keeps its first "before" and its last "after".
     *
     * @param  array<string, array<string, mixed>>  $earlier
     * @param  array<string, array<string, mixed>>  $later
     * @return array<string, array<string, mixed>>
     */
    private static function mergeChanges(array $earlier, array $later): array
    {
        foreach ($later as $field => $change) {
            $earlier[$field] = isset($earlier[$field]) && array_key_exists('old', $earlier[$field])
                ? ['old' => $earlier[$field]['old']] + $change
                : $change;
        }

        return $earlier;
    }

    /**
     * How a record reads in the log: its number, name or title, and its amount.
     */
    public static function labelFor(Model $model): string
    {
        if (method_exists($model, 'activityLabel')) {
            return $model->activityLabel();
        }

        return (string) ($model->getAttribute('name') ?? '#'.$model->getKey());
    }

    private static function userName(Model $user): string
    {
        $name = trim($user->getAttribute('first_name').' '.$user->getAttribute('last_name'));

        return $name !== '' ? $name : (string) ($user->getAttribute('username') ?? $user->getAttribute('email'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Everything that happened to one record, and to the payments, costs and other
     * rows filed under it.
     *
     * @param  Builder<ActivityLog>  $query
     * @param  Model|string  $record  the record, or its morph class with $id
     * @return Builder<ActivityLog>
     */
    public function scopeForRecord($query, Model|string $record, int|string|null $id = null)
    {
        $type = $record instanceof Model ? $record->getMorphClass() : $record;
        $id = $record instanceof Model ? $record->getKey() : $id;

        return $query->where(function ($q) use ($type, $id) {
            $q->where(fn ($own) => $own->where('subject_type', $type)->where('subject_id', $id))
                ->orWhere(fn ($child) => $child->where('parent_type', $type)->where('parent_id', $id));
        });
    }

    /**
     * When it happened, on the local clock rather than the stored UTC one.
     */
    public function localTime(): Carbon
    {
        return $this->created_at->copy()->timezone(config('app.display_timezone'));
    }

    public function userLabel(): string
    {
        return $this->user_name ?? 'System';
    }

    /**
     * "Added", "Edited", "Status updated", "Deleted".
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ADDED => 'Added',
            self::EDITED => 'Edited',
            self::STATUS_UPDATED => 'Status updated',
            self::DELETED => 'Deleted',
            self::IMPORTED => 'Imported',
            default => Str::headline($this->action),
        };
    }

    public function actionColor(): string
    {
        return match ($this->action) {
            self::ADDED => 'success',
            self::EDITED => 'primary',
            self::STATUS_UPDATED => 'info',
            self::DELETED => 'danger',
            self::IMPORTED => 'warning',
            default => 'secondary',
        };
    }

    public function typeLabel(): string
    {
        $key = static::typeKey((string) $this->subject_type);

        return $key ? static::types()[$key]['label'] : Str::headline(class_basename((string) $this->subject_type));
    }

    /**
     * The page to open: the record's own, or — for a payment, cost or document, or a
     * record since deleted — the page of the record it belongs to.
     */
    public function url(): ?string
    {
        $own = static::types()[static::typeKey((string) $this->subject_type) ?? '']['route'] ?? null;

        if ($own && $this->subject_id && $this->action !== self::DELETED) {
            return route($own, $this->subject_id);
        }

        $parent = static::types()[static::typeKey((string) $this->parent_type) ?? '']['route'] ?? null;

        return $parent && $this->parent_id ? route($parent, $this->parent_id) : null;
    }

    /**
     * The changes ready to print.
     *
     * @return array<int, array{field: string, old: string, new: string, hidden: bool}>
     */
    public function changeLines(): array
    {
        $lines = [];

        foreach ($this->field_changes ?? [] as $field => $change) {
            $lines[] = [
                'field' => static::fieldLabel($field),
                'old' => static::displayValue($field, $change['old'] ?? null),
                'new' => static::displayValue($field, $change['new'] ?? null),
                'hidden' => (bool) ($change['hidden'] ?? false),
            ];
        }

        return $lines;
    }

    /**
     * "customer_id" reads "Customer", "cd_rate" reads "CD Rate".
     */
    public static function fieldLabel(string $field): string
    {
        $label = Str::headline(preg_replace('/_id$/', '', $field) ?? $field);

        return preg_replace_callback(
            '/\b(Cd|Sd|Vat|Ait|Rd|At|Lc|Pi|Cbm|Usd|Etd|Eta|Bd|Ctn|Hs|Pol|Pod|Lcl|Fcl)\b/',
            fn (array $match): string => strtoupper($match[1]),
            $label
        ) ?? $label;
    }

    public static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (str_starts_with($field, 'is_') || is_bool($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        $value = (string) $value;

        // Dates are stored with a midnight time; show just the day.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]00:00:00/', $value, $match)) {
            return $match[1];
        }

        // A stored key such as "at_port" or "partial" reads as words.
        if ((str_ends_with($field, 'status') || in_array($field, ['type', 'stage', 'direction', 'method'], true))
            && preg_match('/^[a-z0-9_]+$/', $value)) {
            return Str::headline($value);
        }

        return Str::limit($value, 300);
    }
}
