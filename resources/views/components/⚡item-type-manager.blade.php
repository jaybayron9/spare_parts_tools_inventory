<?php

use App\Models\Item;
use App\Models\ItemType;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $typeName = '';

    protected function rules(): array
    {
        $unique = 'unique:item_types,label';
        if ($this->editingId) {
            $unique .= ",{$this->editingId}";
        }

        return [
            'typeName' => ['required', 'string', 'max:100', $unique],
        ];
    }

    protected function messages(): array
    {
        return [
            'typeName.unique' => 'A type with this name already exists.',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $type = ItemType::findOrFail($id);
        $this->editingId = $type->id;
        $this->typeName = $type->label;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            ItemType::findOrFail($this->editingId)->update(['label' => $this->typeName]);
            session()->flash('type_message', 'Type updated.');
        } else {
            ItemType::create(['label' => $this->typeName]);
            session()->flash('type_message', 'Type created.');
        }

        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('typesUpdated');
    }

    public function delete(int $id): void
    {
        if (ItemType::count() <= 1) {
            session()->flash('type_error', 'At least one type must remain.');

            return;
        }

        $fallback = ItemType::where('id', '!=', $id)->orderBy('id')->first();
        Item::where('item_type_id', $id)->update(['item_type_id' => $fallback->id]);
        ItemType::findOrFail($id)->delete();

        session()->flash('type_message', 'Type deleted. Affected items reassigned.');
        $this->dispatch('typesUpdated');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->typeName = '';
        $this->resetValidation();
    }

    #[Computed]
    public function types()
    {
        return ItemType::withCount('items')->orderBy('label')->get();
    }
}; ?>

<div class="bg-white border border-gray-200 rounded-lg shadow-sm">
    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
        <span class="text-sm font-medium text-gray-700">Manage item types</span>
        <button wire:click="create"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-1.5 rounded-md shadow">
            + New type
        </button>
    </div>

    @if (session('type_message'))
        <div class="mx-4 mt-3 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-green-800 text-sm">
            {{ session('type_message') }}
        </div>
    @endif

    @if (session('type_error'))
        <div class="mx-4 mt-3 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-red-800 text-sm">
            {{ session('type_error') }}
        </div>
    @endif

    @if ($showForm)
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <form wire:submit.prevent="save" class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ $editingId ? 'Edit type name' : 'New type name' }} *
                    </label>
                    <input type="text"
                           wire:model="typeName"
                           placeholder="e.g. Consumable"
                           class="block w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('typeName')
                        <span class="text-red-600 text-xs">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click="cancel"
                            class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                        {{ $editingId ? 'Update' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left font-medium text-gray-700">Type</th>
                <th class="px-4 py-2 text-left font-medium text-gray-700">Items</th>
                <th class="px-4 py-2 text-right font-medium text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($this->types as $type)
                <tr>
                    <td class="px-4 py-2 text-gray-900">{{ $type->label }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $type->items_count }}</td>
                    <td class="px-4 py-2 text-right space-x-3 whitespace-nowrap">
                        <button wire:click="edit({{ $type->id }})"
                                class="text-indigo-600 hover:underline text-sm">Edit</button>
                        <button wire:click="delete({{ $type->id }})"
                                wire:confirm="Delete '{{ $type->label }}'? Items using this type will be moved to another type."
                                @if ($this->types->count() <= 1) disabled class="text-gray-300 cursor-not-allowed text-sm"
                                @else class="text-red-600 hover:underline text-sm" @endif>
                            Delete
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-4 text-center text-gray-400">No types yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
