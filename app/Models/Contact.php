<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'advance_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Prefix of the human-readable customer code (CO0001, CO0002, …). */
    private const CODE_PREFIX = 'CO';

    /** Prefix every shipping mark carries: RTC/MOM, RTC/KARI, … */
    public const SHIPPING_MARK_PREFIX = 'RTC';

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            if (empty($contact->contact_code)) {
                $contact->contact_code = static::generateCode();
            }
        });
    }

    /**
     * Build the next sequential customer code.
     */
    public static function generateCode(): string
    {
        $last = static::where('contact_code', 'like', self::CODE_PREFIX.'%')
            ->orderByDesc('id')->value('contact_code');
        $next = $last ? ((int) substr($last, strlen(self::CODE_PREFIX))) + 1 : 1;

        return self::CODE_PREFIX.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Propose a shipping mark for a customer: the prefix plus the first three
     * letters of their name. If that mark is already another customer's, it
     * widens to four letters and then counts up, so no two customers share one.
     */
    public static function suggestShippingMark(?string $name, ?string $businessName = null, ?int $ignoreId = null): string
    {
        $letters = strtoupper(preg_replace('/[^a-z]/i', '', $name ?: $businessName ?: '') ?? '');

        if ($letters === '') {
            $letters = 'CUS';
        }

        $isTaken = fn (string $mark): bool => static::where('shipping_mark', $mark)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        foreach ([3, 4] as $length) {
            $candidate = self::SHIPPING_MARK_PREFIX.'/'.substr($letters, 0, $length);

            if (! $isTaken($candidate)) {
                return $candidate;
            }
        }

        $base = self::SHIPPING_MARK_PREFIX.'/'.substr($letters, 0, 4);

        for ($suffix = 2; $isTaken($base.$suffix); $suffix++) {
            // Walk up until an unused mark turns up.
        }

        return $base.$suffix;
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    /**
     * The portal login (User) for this customer, if one has been created.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'contact_id');
    }

    /**
     * Quotations where this contact is the customer (incl. portal requests).
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'customer_id')->latest();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Every contact is a customer; the scope is kept so call sites read clearly.
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query;
    }
}
