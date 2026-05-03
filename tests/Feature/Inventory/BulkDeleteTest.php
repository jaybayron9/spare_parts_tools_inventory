<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_removes_selected_items(): void
    {
        $items = Item::factory()->count(5)->create();

        $toDelete = $items->take(3)->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test('item-manager')
            ->set('selectedIds', $toDelete)
            ->call('bulkDelete')
            ->assertSet('selectedIds', [])
            ->assertSet('selectAll', false);

        $this->assertSame(2, Item::count());
        $this->assertDatabaseMissing('items', ['id' => $items[0]->id]);
        $this->assertDatabaseHas('items', ['id' => $items[3]->id]);
    }

    public function test_bulk_delete_with_no_selection_does_nothing(): void
    {
        Item::factory()->count(3)->create();

        Livewire::test('item-manager')
            ->set('selectedIds', [])
            ->call('bulkDelete');

        $this->assertSame(3, Item::count());
    }

    public function test_select_all_fills_current_page_ids(): void
    {
        Item::factory()->count(15)->create();

        $component = Livewire::test('item-manager')
            ->set('selectAll', true);

        $selected = $component->get('selectedIds');
        $this->assertCount(10, $selected, 'Select all should select the 10 items on page 1.');
    }

    public function test_selection_clears_on_search_change(): void
    {
        $items = Item::factory()->count(3)->create();

        Livewire::test('item-manager')
            ->set('selectedIds', [$items->first()->id])
            ->set('search', 'something')
            ->assertSet('selectedIds', [])
            ->assertSet('selectAll', false);
    }

    public function test_selection_clears_on_filter_change(): void
    {
        $items = Item::factory()->count(3)->create();

        Livewire::test('item-manager')
            ->set('selectedIds', [$items->first()->id])
            ->set('filterType', Item::TYPE_TOOL)
            ->assertSet('selectedIds', [])
            ->assertSet('selectAll', false);
    }

    public function test_individual_delete_removes_id_from_selection(): void
    {
        $items = Item::factory()->count(3)->create();
        $ids = $items->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test('item-manager')
            ->set('selectedIds', $ids)
            ->call('delete', $items->first()->id)
            ->assertSet('selectedIds', array_values(array_slice($ids, 1)));
    }
}
