<?php

namespace App\Models;

use Database\Factories\ContainerDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerDocument extends Model
{
    /** @use HasFactory<ContainerDocumentFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * The official documents that accompany a shipment.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            'Bill of Lading (B/L)',
            'Invoice',
            'Packing List',
            'Detail Packing List',
            'Proforma Invoice',
            'LC Copy',
            'Insurance Copy',
            'Certificate of Origin',
            'Other',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
