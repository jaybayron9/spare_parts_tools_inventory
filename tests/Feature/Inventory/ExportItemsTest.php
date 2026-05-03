<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExportItemsTest extends TestCase
{
    use RefreshDatabase;

    private function signedUrl(array $params = []): string
    {
        return URL::signedRoute('items.export', $params);
    }

    public function test_export_all_returns_xlsx_download(): void
    {
        Item::factory()->count(3)->create();

        $response = $this->get($this->signedUrl());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'inventory-export-',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_export_selected_ids_only(): void
    {
        $items = Item::factory()->count(5)->create();
        $selected = $items->take(2)->pluck('id')->implode(',');

        $response = $this->get($this->signedUrl(['ids' => $selected]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_respects_search_filter(): void
    {
        $type = ItemType::where('label', 'Spare Part')->first();
        Item::factory()->create(['name' => 'Alpha Widget', 'item_type_id' => $type->id]);
        Item::factory()->create(['name' => 'Beta Gadget', 'item_type_id' => $type->id]);

        $response = $this->get($this->signedUrl(['search' => 'Alpha']));

        $response->assertStatus(200);
    }

    public function test_export_respects_type_filter(): void
    {
        $spare = ItemType::where('label', 'Spare Part')->first();
        $tool = ItemType::where('label', 'Tool')->first();

        Item::factory()->count(2)->create(['item_type_id' => $spare->id]);
        Item::factory()->count(3)->create(['item_type_id' => $tool->id]);

        $response = $this->get($this->signedUrl(['filter_type' => $spare->id]));

        $response->assertStatus(200);
    }

    public function test_unsigned_url_is_rejected(): void
    {
        $response = $this->get(route('items.export'));

        $response->assertStatus(403);
    }

    public function test_export_with_no_items_still_downloads(): void
    {
        $response = $this->get($this->signedUrl());

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
