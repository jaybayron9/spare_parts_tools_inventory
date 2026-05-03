<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemType extends Model
{
    protected $fillable = ['label'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
