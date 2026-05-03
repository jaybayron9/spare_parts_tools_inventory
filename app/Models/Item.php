<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'vendor',
        'brand',
        'equipment_system',
        'contract',
        'is_critical',
        'uom',
        'install_remarks',
        'sku',
        'item_type_id',
        'category',
        'quantity',
        'reorder_level',
        'leadtime',
        'unit_price',
        'location',
        'date_purchased',
        'service_life_yrs',
        'eul_yrs',
        'replacement_frequency',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reorder_level' => 'integer',
        'unit_price' => 'decimal:2',
        'is_critical' => 'boolean',
        'date_purchased' => 'date',
        'service_life_yrs' => 'integer',
        'eul_yrs' => 'integer',
    ];

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->itemType?->label ?? '—';
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }
}
