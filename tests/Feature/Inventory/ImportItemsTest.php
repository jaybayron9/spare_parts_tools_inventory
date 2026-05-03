<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ImportItemsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): UploadedFile
    {
        $bytes = file_get_contents(base_path('tests/Fixtures/example.xlsx'));

        return UploadedFile::fake()->createWithContent('example.xlsx', $bytes);
    }

    public function test_upload_previews_rows(): void
    {
        $component = Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->assertSet('headerError', '');

        $this->assertGreaterThan(0, count($component->get('preview')));
    }

    public function test_confirm_inserts_rows(): void
    {
        $this->assertSame(0, Item::count());

        Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->call('confirm');

        $this->assertGreaterThan(0, Item::count());
        $this->assertNotNull(Item::where('sku', 'SEN02133')->first());
    }

    public function test_re_import_upserts_not_duplicates(): void
    {
        Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->call('confirm');

        $afterFirst = Item::count();
        $this->assertGreaterThan(0, $afterFirst);

        Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->call('confirm')
            ->assertSet('insertedCount', 0)
            ->assertSet('updatedCount', $afterFirst);

        $this->assertSame($afterFirst, Item::count(), 'Re-import should not change row count.');
    }

    public function test_blank_part_number_rows_are_skipped(): void
    {
        Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->call('confirm');

        // The fixture contains banner/section rows like "HVAC" with no part number;
        // none of those should land in the items table.
        $this->assertSame(0, Item::whereNull('sku')->count());
        $this->assertSame(0, Item::where('sku', '')->count());
    }

    public function test_invalid_mime_is_rejected(): void
    {
        $bad = UploadedFile::fake()->create('items.csv', 10, 'text/csv');

        Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $bad)
            ->assertHasErrors(['file' => 'mimes']);
    }

    public function test_all_ready_rows_are_pre_selected_after_upload(): void
    {
        $component = Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture());

        $preview = $component->get('preview');
        $selectedRows = $component->get('selectedRows');

        $readyIndices = array_keys(array_filter($preview, fn ($p) => $p['attributes'] !== null));
        $this->assertCount(count($readyIndices), $selectedRows);
    }

    public function test_deselecting_all_rows_imports_nothing(): void
    {
        Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->call('deselectAllRows')
            ->call('confirm')
            ->assertSet('insertedCount', 0)
            ->assertSet('updatedCount', 0);

        $this->assertSame(0, Item::count());
    }

    public function test_partial_selection_imports_only_checked_rows(): void
    {
        $component = Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture());

        $preview = $component->get('preview');
        $readyIndices = array_keys(array_filter($preview, fn ($p) => $p['attributes'] !== null));

        // Select only the first 2 ready rows.
        $twoIndices = array_map('strval', array_slice($readyIndices, 0, 2));

        $component
            ->set('selectedRows', $twoIndices)
            ->call('confirm');

        $this->assertSame(2, Item::count());
    }

    public function test_deselect_all_then_select_all_restores_full_selection(): void
    {
        $component = Livewire::test('import-items')
            ->call('toggle')
            ->set('file', $this->fixture())
            ->call('deselectAllRows')
            ->assertSet('selectedRows', [])
            ->call('selectAllRows');

        $preview = $component->get('preview');
        $selectedRows = $component->get('selectedRows');
        $readyCount = count(array_filter($preview, fn ($p) => $p['attributes'] !== null));

        $this->assertCount($readyCount, $selectedRows);
    }
}
