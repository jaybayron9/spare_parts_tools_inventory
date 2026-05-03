<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    public const TYPE_SPARE_PART = 'spare_part';

    public const TYPE_TOOL = 'tool';

    public const TYPES = [
        self::TYPE_SPARE_PART => 'Spare Part',
        self::TYPE_TOOL => 'Tool',
    ];

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
        'type',
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

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity <= $this->reorder_level;
    }
}
