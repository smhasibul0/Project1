<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderScan extends Model
{
    use RecordsActivity;

    /** Its history shows on the record it belongs to. */
    protected string $activityParent = 'order';

    protected $guarded = [];

    /**
     * The points on the journey a carton label is scanned at, in the order they
     * happen, each with the goods status reaching it puts the order into.
     *
     * @var array<string, array{label: string, status: string, action: string}>
     */
    public const STAGES = [
        'china_warehouse' => [
            'label' => 'Received at China warehouse',
            'status' => 'at_china_warehouse',
            'action' => 'Receive at China warehouse',
        ],
        'container_loaded' => [
            'label' => 'Loaded into container',
            'status' => 'shipped',
            'action' => 'Load into a container',
        ],
        'at_port' => [
            'label' => 'Arrived at port',
            'status' => 'at_port',
            'action' => 'Mark arrived at port',
        ],
        'bd_warehouse' => [
            'label' => 'Received at BD warehouse',
            'status' => 'at_bd_warehouse',
            'action' => 'Receive at BD warehouse',
        ],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cartons' => 'decimal:2',
        ];
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage]['label'] ?? ucfirst(str_replace('_', ' ', (string) $this->stage));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /**
     * "12 cartons — Received at China warehouse — Order OR0001".
     */
    public function activityLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->cartons, 2), '0'), '.').' cartons — '.$this->stageLabel()
            .($this->order ? ' — Order '.$this->order->order_no : '');
    }
}
