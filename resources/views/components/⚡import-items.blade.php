<?php

use App\Imports\InvalidXlsxException;
use App\Imports\ItemRowMapper;
use App\Imports\ItemsXlsxParser;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate(['file', 'mimes:xlsx', 'max:5120'])]
    public ?TemporaryUploadedFile $file = null;

    public bool $open = false;

    /** @var array<int, array{row: int, attributes: ?array<string, mixed>, errors: list<string>, warnings: list<string>}> */
    public array $preview = [];

    /** @var list<string> Indices (as strings) of preview rows the user has checked for import. */
    public array $selectedRows = [];

    public string $headerError = '';

    public ?int $insertedCount = null;

    public ?int $updatedCount = null;

    public function toggle(): void
    {
        $this->open = ! $this->open;
        if (! $this->open) {
            $this->resetState();
        }
    }

    public function updatedFile(): void
    {
        $this->resetSummary();
        $this->validate();

        $path = $this->file->getRealPath();

        try {
            $rows = (new ItemsXlsxParser)->parse($path);
        } catch (InvalidXlsxException $e) {
            $this->headerError = $e->getMessage();
            $this->preview = [];
            $this->selectedRows = [];

            return;
        }

        $mapper = new ItemRowMapper;
        $preview = [];
        foreach ($rows as $i => $row) {
            $result = $mapper->map($row);
            $preview[] = [
                'row' => $i + 3, // +3 → banner row 1, header row 2, data starts at row 3
                'attributes' => $result['attributes'],
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
            ];
        }

        $this->preview = $preview;
        $this->selectedRows = $this->readyIndices();
    }

    public function selectAllRows(): void
    {
        $this->selectedRows = $this->readyIndices();
    }

    public function deselectAllRows(): void
    {
        $this->selectedRows = [];
    }

    public function confirm(): void
    {
        if (empty($this->preview) || empty($this->selectedRows)) {
            $this->insertedCount = 0;
            $this->updatedCount = 0;

            return;
        }

        $selected = array_map('intval', $this->selectedRows);

        $valid = array_values(array_filter(
            array_map(fn ($p, $i) => in_array($i, $selected, true) ? $p['attributes'] : null, $this->preview, array_keys($this->preview)),
            fn ($a) => $a !== null,
        ));

        if (empty($valid)) {
            $this->insertedCount = 0;
            $this->updatedCount = 0;

            return;
        }

        $uniqueSkus = array_values(array_unique(array_column($valid, 'sku')));
        $existingSkus = Item::query()
            ->whereIn('sku', $uniqueSkus)
            ->pluck('sku')
            ->all();

        $updateColumns = array_values(array_diff(array_keys($valid[0]), ['sku']));

        DB::transaction(function () use ($valid, $updateColumns) {
            foreach (array_chunk($valid, 100) as $chunk) {
                Item::upsert($chunk, ['sku'], $updateColumns);
            }
        });

        $this->updatedCount = count($existingSkus);
        $this->insertedCount = count($uniqueSkus) - count($existingSkus);
        $this->preview = [];
        $this->selectedRows = [];
        $this->reset('file');
        $this->dispatch('items-imported');
    }

    public function cancel(): void
    {
        $this->resetState();
    }

    protected function resetState(): void
    {
        $this->reset(['file', 'preview', 'selectedRows', 'headerError', 'insertedCount', 'updatedCount']);
        $this->resetValidation();
    }

    protected function resetSummary(): void
    {
        $this->insertedCount = null;
        $this->updatedCount = null;
        $this->headerError = '';
    }

    /** @return list<string> */
    protected function readyIndices(): array
    {
        return array_values(array_map(
            'strval',
            array_keys(array_filter($this->preview, fn ($p) => $p['attributes'] !== null)),
        ));
    }

    public function with(): array
    {
        $totalReady = count(array_filter($this->preview, fn ($p) => $p['attributes'] !== null));
        $skippedCount = count(array_filter($this->preview, fn ($p) => $p['attributes'] === null));

        return [
            'selectedCount' => count($this->selectedRows),
            'totalReadyCount' => $totalReady,
            'skippedCount' => $skippedCount,
            'allSelected' => $totalReady > 0 && count($this->selectedRows) === $totalReady,
        ];
    }
}; ?>

<div class="bg-white border border-gray-200 rounded-lg shadow-sm">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Import from XLSX</h3>
            <p class="text-xs text-gray-500">Upload a spare-parts spreadsheet. Existing SKUs will be updated; new ones inserted.</p>
        </div>
        <button type="button" wire:click="toggle"
                class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50">
            {{ $open ? 'Close' : 'Open Importer' }}
        </button>
    </div>

    @if ($open)
        <div class="p-4 space-y-4">
            @if ($insertedCount !== null)
                <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-emerald-800 text-sm">
                    Imported: {{ $insertedCount }} inserted, {{ $updatedCount }} updated.
                </div>
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">XLSX file</label>
                    <input type="file" accept=".xlsx" wire:model="file"
                           class="mt-1 block text-sm">
                </div>
                <div wire:loading wire:target="file" class="text-xs text-gray-500">Parsing…</div>
                @error('file') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>

            @if ($headerError)
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-2 text-red-800 text-sm">
                    {{ $headerError }}
                </div>
            @endif

            @if (! empty($preview))
                <div class="flex items-center justify-between text-sm text-gray-700">
                    <div>
                        <span class="font-medium">{{ $selectedCount }}</span> of
                        <span class="font-medium">{{ $totalReadyCount }}</span> rows selected,
                        <span class="font-medium">{{ $skippedCount }}</span> skipped.
                    </div>
                    <div class="flex gap-3 text-xs">
                        <button type="button" wire:click="selectAllRows"
                                class="text-indigo-600 hover:underline disabled:opacity-40"
                                @disabled($allSelected)>
                            Select all
                        </button>
                        <button type="button" wire:click="deselectAllRows"
                                class="text-indigo-600 hover:underline disabled:opacity-40"
                                @disabled(count($selectedRows) === 0)>
                            Deselect all
                        </button>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-md max-h-96 overflow-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-2 py-1 w-6"></th>
                                <th class="px-2 py-1 text-left font-medium text-gray-600">Row</th>
                                <th class="px-2 py-1 text-left font-medium text-gray-600">Status</th>
                                <th class="px-2 py-1 text-left font-medium text-gray-600">SKU</th>
                                <th class="px-2 py-1 text-left font-medium text-gray-600">Name</th>
                                <th class="px-2 py-1 text-left font-medium text-gray-600">Equip/System</th>
                                <th class="px-2 py-1 text-right font-medium text-gray-600">Qty</th>
                                <th class="px-2 py-1 text-left font-medium text-gray-600">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($preview as $index => $p)
                                @php $isReady = $p['attributes'] !== null; @endphp
                                @php $isChecked = $isReady && in_array((string) $index, $selectedRows, true); @endphp
                                <tr class="{{ ! $isReady ? 'bg-amber-50' : (! $isChecked ? 'opacity-50' : '') }}">
                                    <td class="px-2 py-1">
                                        @if ($isReady)
                                            <input type="checkbox"
                                                   wire:model.live="selectedRows"
                                                   value="{{ $index }}"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 text-gray-500">{{ $p['row'] }}</td>
                                    <td class="px-2 py-1">
                                        @if (! $isReady)
                                            <span class="inline-block rounded-full px-2 py-0.5 bg-amber-100 text-amber-800">
                                                skipped
                                            </span>
                                        @elseif ($isChecked)
                                            <span class="inline-block rounded-full px-2 py-0.5 bg-emerald-100 text-emerald-800">
                                                ready
                                            </span>
                                        @else
                                            <span class="inline-block rounded-full px-2 py-0.5 bg-gray-100 text-gray-500">
                                                excluded
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1">{{ $p['attributes']['sku'] ?? '—' }}</td>
                                    <td class="px-2 py-1">{{ $p['attributes']['name'] ?? '—' }}</td>
                                    <td class="px-2 py-1">{{ $p['attributes']['equipment_system'] ?? '' }}</td>
                                    <td class="px-2 py-1 text-right">{{ $p['attributes']['quantity'] ?? '' }}</td>
                                    <td class="px-2 py-1 text-gray-600">
                                        @if (! empty($p['errors']))
                                            <span class="text-red-700">{{ implode('; ', $p['errors']) }}</span>
                                        @elseif (! empty($p['warnings']))
                                            <span class="text-amber-700">{{ implode('; ', $p['warnings']) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancel"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirm"
                            wire:loading.attr="disabled"
                            wire:target="confirm"
                            wire:loading.class="opacity-75 cursor-not-allowed"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 disabled:opacity-60">
                        <svg wire:loading wire:target="confirm" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Confirm import ({{ $selectedCount }} rows)
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
