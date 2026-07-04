<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected static function booted(): void
    {
        // Auto-generate a human-readable code on creation: CO#### for customers
        // (and "both"), SU#### for suppliers.
        static::creating(function (Contact $contact) {
            if (empty($contact->contact_code)) {
                $contact->contact_code = static::generateCode($contact->type);
            }
        });
    }

    /**
     * Build the next sequential contact code for the given type.
     */
    public static function generateCode(?string $type): string
    {
        $prefix = in_array($type, ['customer', 'both'], true) ? 'CO' : 'CU';

        $last = static::where('contact_code', 'like', $prefix.'%')->orderByDesc('id')->value('contact_code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Contacts acting as suppliers (type supplier or both).
     */
    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }

    /**
     * Contacts acting as customers (type customer or both).
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', ['customer', 'both']);
    }
}
