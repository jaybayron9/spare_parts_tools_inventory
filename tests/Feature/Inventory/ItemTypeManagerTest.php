<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemTypeManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_new_type(): void
    {
        Livewire::test('⚡item-type-manager')
            ->call('create')
            ->set('typeName', 'Consumable')
            ->call('save');

        $this->assertDatabaseHas('item_types', ['label' => 'Consumable']);
    }

    public function test_cannot_create_duplicate_type_label(): void
    {
        ItemType::create(['label' => 'Consumable']);

        Livewire::test('⚡item-type-manager')
            ->call('create')
            ->set('typeName', 'Consumable')
            ->call('save')
            ->assertHasErrors(['typeName']);
    }

    public function test_can_edit_an_existing_type(): void
    {
        $type = ItemType::create(['label' => 'Custom Type']);

        Livewire::test('⚡item-type-manager')
            ->call('edit', $type->id)
            ->assertSet('typeName', 'Custom Type')
            ->set('typeName', 'Electrical Part')
            ->call('save');

        $this->assertDatabaseHas('item_types', ['id' => $type->id, 'label' => 'Electrical Part']);
        $this->assertDatabaseMissing('item_types', ['label' => 'Custom Type']);
    }

    public function test_can_delete_a_type_and_items_are_reassigned(): void
    {
        // Clear seeded types to control the fallback precisely
        ItemType::query()->delete();

        $typeA = ItemType::create(['label' => 'Type A']);
        $typeB = ItemType::create(['label' => 'Type B']);

        $items = Item::factory()->count(3)->create(['item_type_id' => $typeA->id]);

        Livewire::test('⚡item-type-manager')
            ->call('delete', $typeA->id);

        $this->assertDatabaseMissing('item_types', ['id' => $typeA->id]);

        foreach ($items as $item) {
            $this->assertSame($typeB->id, $item->fresh()->item_type_id);
        }
    }

    public function test_cannot_delete_the_last_remaining_type(): void
    {
        // Clear seeded types so only one remains
        ItemType::query()->delete();
        $type = ItemType::create(['label' => 'Only Type']);

        Livewire::test('⚡item-type-manager')
            ->call('delete', $type->id);

        $this->assertDatabaseHas('item_types', ['id' => $type->id]);
        $this->assertSame(1, ItemType::count());
    }

    public function test_filter_by_type_uses_item_type_id(): void
    {
        $typeA = ItemType::create(['label' => 'Custom A']);
        $typeB = ItemType::create(['label' => 'Custom B']);

        Item::factory()->count(3)->create(['item_type_id' => $typeA->id]);
        Item::factory()->count(2)->create(['item_type_id' => $typeB->id]);

        $component = Livewire::test('⚡item-manager')
            ->set('filterType', (string) $typeA->id);

        $items = $component->get('items');
        $this->assertSame(3, $items->total());
        $this->assertTrue($items->every(fn ($i) => $i->item_type_id === $typeA->id));
    }

    public function test_deleting_active_filter_type_resets_filter(): void
    {
        $typeA = ItemType::create(['label' => 'Custom A']);
        ItemType::create(['label' => 'Custom B']);

        $manager = Livewire::test('⚡item-manager')
            ->set('filterType', (string) $typeA->id);

        Livewire::test('⚡item-type-manager')
            ->call('delete', $typeA->id);

        $manager->dispatch('typesUpdated')
            ->assertSet('filterType', '');
    }
}
