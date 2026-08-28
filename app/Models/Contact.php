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
