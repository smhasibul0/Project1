<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    /**
     * Borrowed means the company took the money and owes it; lent means the
     * company handed it out and is owed it back.
     *
     * @var array<string, string>
     */
    public const DIRECTIONS = [
        'borrowed' => 'Borrowed',
        'lent' => 'Lent',
    ];

    /**
     * Interest can be a yearly percentage of the principal, one flat charge
     * for the whole loan, or nothing at all.
     *
     * @var array<string, string>
     */
    public const INTEREST_TYPES = [
        'none' => 'No interest',
        'percentage' => 'Percentage a year',
        'fixed' => 'Fixed amount',
    ];

    /**
     * @var array<string, string>
     */
    public const COUNTERPARTY_TYPES = [
        'bank' => 'Bank',
        'individual' => 'Individual',
        'company' => 'Company',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'active' => 'Active',
        'settled' => 'Settled',
        'written_off' => 'Written off',
    ];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'principal' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'interest_amount' => 'decimal:2',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * The next code in sequence for a direction, e.g. BRW-0007 or LND-0003.
     */
    public static function nextCode(string $direction): string
    {
        $prefix = $direction === 'borrowed' ? 'BRW' : 'LND';
        $last = static::where('direction', $direction)->orderByDesc('id')->value('loan_code');
        $number = $last && preg_match('/(\d+)$/', $last, $match) ? ((int) $match[1]) + 1 : 1;

        return $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public function scopeBorrowed(Builder $query): Builder
    {
        return $query->where('direction', 'borrowed');
    }

    public function scopeLent(Builder $query): Builder
    {
        return $query->where('direction', 'lent');
    }

    public function isBorrowed(): bool
    {
        return $this->direction === 'borrowed';
    }

    /**
     * Interest owed so far.
     *
     * A fixed charge is the whole amount from day one — it does not accrue.
     * A percentage is simple interest on the principal for the days the money
     * has actually been out, stopping at the due date so an overdue loan does
     * not quietly keep growing without someone deciding it should.
     */
    public function interestToDate(?Carbon $asOf = null): float
    {
        if ($this->interest_type === 'none' || ! $this->start_date) {
            return 0.0;
        }

        if ($this->interest_type === 'fixed') {
            return round((float) $this->interest_amount, 2);
        }

        $end = $asOf ?? Carbon::now();
        if ($this->due_date && $end->gt($this->due_date)) {
            $end = $this->due_date->copy();
        }

        $days = max(0, $this->start_date->diffInDays($end));

        return round((float) $this->principal * ((float) $this->interest_rate / 100) * ($days / 365), 2);
    }

    /**
     * Principal plus the interest owed so far.
     */
    public function totalPayable(?Carbon $asOf = null): float
    {
        return round((float) $this->principal + $this->interestToDate($asOf), 2);
    }

    /**
     * Everything paid against the loan — repaid by the company on a borrowing,
     * received back on a lending.
     */
    public function paidTotal(): float
    {
        return round((float) $this->payments->sum(fn (LoanPayment $p) => (float) $p->amount), 2);
    }

    /**
     * What is still to change hands.
     */
    public function outstanding(?Carbon $asOf = null): float
    {
        return round(max(0, $this->totalPayable($asOf) - $this->paidTotal()), 2);
    }

    /**
     * Settlement state, from the payments rather than the status field: the
     * status only records a loan someone has deliberately closed or written off.
     */
    public function paymentStatus(): string
    {
        if ($this->status === 'written_off') {
            return 'written_off';
        }

        $paid = $this->paidTotal();

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $this->outstanding() <= 0.005 ? 'settled' : 'partial';
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->status === 'active'
            && $this->outstanding() > 0.005
            && $this->due_date->isPast();
    }

    /**
     * "12.00% a year", "৳ 5,000.00 fixed" or "No interest" for display.
     */
    public function interestLabel(): string
    {
        return match ($this->interest_type) {
            'percentage' => number_format((float) $this->interest_rate, 2).'% a year',
            'fixed' => '৳ '.number_format((float) $this->interest_amount, 2).' fixed',
            default => 'No interest',
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function counterpartyTypeLabel(): string
    {
        return self::COUNTERPARTY_TYPES[$this->counterparty_type] ?? $this->counterparty_type;
    }
}
