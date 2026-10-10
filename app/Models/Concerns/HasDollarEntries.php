<?php

namespace App\Models\Concerns;

use App\Models\DollarTransaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record that can be paid (or received) in dollars from a payment account's dollar
 * balance instead of its taka.
 */
trait HasDollarEntries
{
    public function dollarEntries(): MorphMany
    {
        return $this->morphMany(DollarTransaction::class, 'transactionable');
    }

    /**
     * "$40.00 @ 122.4" when the record was paid in dollars.
     */
    public function dollarNote(): ?string
    {
        $entry = $this->dollarEntries->first();

        return $entry ? '$'.number_format((float) $entry->usd_amount, 2).' @ '.DollarTransaction::rate($entry->rate) : null;
    }
}
