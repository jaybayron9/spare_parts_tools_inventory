<?php

namespace Tests\Unit\Models;

use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_relationship_returns_items(): void
    {
        $type = ItemType::create(['label' => 'Consumable']);
        Item::factory()->count(2)->create(['item_type_id' => $type->id]);

        $this->assertSame(2, $type->items()->count());
    }
}
