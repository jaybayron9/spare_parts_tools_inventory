<?php

use App\Models\Item;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[On('items-imported')]
    public function refreshAfterImport(): void
    {
        $this->resetPage();
        $this->reset('selectedIds', 'selectAll');
    }

    public ?int $editingId = null;

    public bool $showForm = false;

    /** @var list<string> Item IDs selected on the current page (stored as strings by wire:model checkboxes). */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public string $name = '';

    public string $sku = '';

    public string $type = Item::TYPE_SPARE_PART;

    public string $category = '';

    public int $quantity = 0;

    public int $reorder_level = 0;

    public float $unit_price = 0;

    public string $location = '';

    public string $notes = '';

    public string $search = '';

    public string $filterType = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public array $columnOrder = [
        'name', 'sku', 'type', 'category', 'quantity',
        'reorder_level', 'unit_price', 'location',
    ];

    public array $columnLabels = [
        'name' => 'Name',
        'sku' => 'SKU',
        'type' => 'Type',
        'category' => 'Category',
        'quantity' => 'Quantity',
        'reorder_level' => 'Reorder Level',
        'unit_price' => 'Unit Price',
        'location' => 'Location',
    ];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:items,sku'.($this->editingId ? ','.$this->editingId : ''),
            'type' => 'required|in:'.implode(',', array_keys(Item::TYPES)),
            'category' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'location' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ];
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedIds = $this->items->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->selectedIds = [];
        }
    }

    public function updatedSelectedIds(): void
    {
        $pageIds = $this->items->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selectAll = count($pageIds) > 0 && empty(array_diff($pageIds, $this->selectedIds));
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $count = count($this->selectedIds);
        Item::whereIn('id', $this->selectedIds)->delete();
        $this->reset('selectedIds', 'selectAll');
        $this->resetPage();
        session()->flash('message', "Deleted {$count} item(s).");
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->reset('selectedIds', 'selectAll');
        $this->resetPage();
    }

    public function updateColumnOrder(array $order): void
    {
        $this->columnOrder = array_values(array_intersect($order, array_keys($this->columnLabels)));
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->reset('selectedIds', 'selectAll');
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
        $this->reset('selectedIds', 'selectAll');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = Item::findOrFail($id);
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->sku = $item->sku;
        $this->type = $item->type;
        $this->category = $item->category ?? '';
        $this->quantity = $item->quantity;
        $this->reorder_level = $item->reorder_level;
        $this->unit_price = (float) $item->unit_price;
        $this->location = $item->location ?? '';
        $this->notes = $item->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Item::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Item updated.');
        } else {
            Item::create($data);
            session()->flash('message', 'Item created.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Item::findOrFail($id)->delete();
        $this->selectedIds = array_values(array_diff($this->selectedIds, [(string) $id]));
        session()->flash('message', 'Item deleted.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->reset(['name', 'sku', 'category', 'quantity', 'reorder_level', 'unit_price', 'location', 'notes']);
        $this->type = Item::TYPE_SPARE_PART;
        $this->resetValidation();
    }

    #[Computed]
    public function items()
    {
        return Item::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('category', 'like', "%{$this->search}%");
            }))
            ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div class="space-y-6">
    @if (session('message'))
        <div class="rounded-md bg-green-50 border border-green-200 px-4 py-2 text-green-800 text-sm">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-3 justify-between">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name, SKU, category..."
                       class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Filter by Type</label>
                <select wire:model.live="filterType"
                        class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2">
                    <option value="">All</option>
                    @foreach (\App\Models\Item::TYPES as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if (count($selectedIds) > 0)
                <button wire:click="bulkDelete"
                        wire:confirm="Delete {{ count($selectedIds) }} item(s)? This cannot be undone."
                        wire:loading.attr="disabled"
                        wire:target="bulkDelete"
                        wire:loading.class="opacity-75 cursor-not-allowed"
                        class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-md shadow">
                    <svg wire:loading wire:target="bulkDelete" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Delete selected ({{ count($selectedIds) }})
                </button>
            @endif
            <button wire:click="create"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md shadow">
                + New Item
            </button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">
                {{ $editingId ? 'Edit Item' : 'New Item' }}
            </h3>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">Name *</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                    @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">SKU *</label>
                    <input type="text" wire:model="sku" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                    @error('sku') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Type *</label>
                    <select wire:model="type" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                        @foreach (\App\Models\Item::TYPES as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Category</label>
                    <input type="text" wire:model="category" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium">Quantity *</label>
                    <input type="number" min="0" wire:model="quantity" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                    @error('quantity') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Reorder Level *</label>
                    <input type="number" min="0" wire:model="reorder_level" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                    @error('reorder_level') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Unit Price *</label>
                    <input type="number" step="0.01" min="0" wire:model="unit_price" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                    @error('unit_price') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Location</label>
                    <input type="text" wire:model="location" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium">Notes</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm"></textarea>
                </div>
                <div class="md:col-span-2 flex gap-2 justify-end">
                    <button type="button" wire:click="cancel"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            wire:loading.class="opacity-75 cursor-not-allowed"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                        <svg wire:loading wire:target="save" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $editingId ? 'Update' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <p class="px-4 pt-3 text-xs text-gray-500">
            Tip: drag column headers left/right to reorder. Click a header to sort.
        </p>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr id="column-headers">
                    <th class="px-3 py-2 w-8">
                        <input type="checkbox" wire:model.live="selectAll"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               title="Select all on this page">
                    </th>
                    @foreach ($columnOrder as $col)
                        <th data-col="{{ $col }}"
                            class="px-4 py-2 text-left font-medium text-gray-700 cursor-move select-none whitespace-nowrap"
                            title="Drag to reorder">
                            <button type="button" wire:click="sortBy('{{ $col }}')" class="hover:underline">
                                {{ $columnLabels[$col] }}
                                @if ($sortField === $col)
                                    <span class="text-indigo-600">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                    @endforeach
                    <th class="px-4 py-2 text-right font-medium text-gray-700 sticky right-0 bg-gray-50 shadow-[-1px_0_0_0_#e5e7eb]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->items as $item)
                    <tr class="{{ $item->is_low_stock ? 'bg-amber-50' : '' }}">
                        <td class="px-3 py-2 w-8">
                            <input type="checkbox" wire:model.live="selectedIds" value="{{ $item->id }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        @foreach ($columnOrder as $col)
                            <td class="px-4 py-2 whitespace-nowrap">
                                @switch($col)
                                    @case('type')
                                        <span class="inline-block rounded-full px-2 py-0.5 text-xs
                                            {{ $item->type === 'tool' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $item->type_label }}
                                        </span>
                                        @break
                                    @case('quantity')
                                        {{ $item->quantity }}
                                        @if ($item->is_low_stock)
                                            <span class="text-amber-700 text-xs ml-1">(low)</span>
                                        @endif
                                        @break
                                    @case('unit_price')
                                        {{ number_format((float) $item->unit_price, 2) }}
                                        @break
                                    @default
                                        {{ $item->{$col} }}
                                @endswitch
                            </td>
                        @endforeach
                        <td class="px-4 py-2 whitespace-nowrap text-right space-x-2 sticky right-0 shadow-[-1px_0_0_0_#e5e7eb] {{ $item->is_low_stock ? 'bg-amber-50' : 'bg-white' }}">
                            <button wire:click="edit({{ $item->id }})"
                                    class="text-indigo-600 hover:underline text-sm">Edit</button>
                            <button wire:click="delete({{ $item->id }})"
                                    wire:confirm="Delete this item?"
                                    class="text-red-600 hover:underline text-sm">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columnOrder) + 2 }}" class="px-4 py-6 text-center text-gray-400">
                            No items yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $this->items->links() }}
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        document.addEventListener('livewire:init', () => {
            const initSortable = () => {
                const headers = document.getElementById('column-headers');
                if (!headers || headers.dataset.sortableInit) return;
                headers.dataset.sortableInit = '1';
                Sortable.create(headers, {
                    animation: 150,
                    draggable: 'th[data-col]',
                    onEnd: () => {
                        const order = Array.from(headers.querySelectorAll('th[data-col]'))
                            .map(th => th.dataset.col);
                        @this.call('updateColumnOrder', order);
                    },
                });
            };
            initSortable();
            Livewire.hook('morph.updated', initSortable);
        });
    </script>
    @endpush
</div>
