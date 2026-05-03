<?php

use App\Mail\InventorySummaryMail;
use App\Models\NotificationSchedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $editingId = null;
    public bool $showForm = false;

    public string $email = '';
    public string $frequency = 'daily';
    public string $send_at = '08:00';
    public bool $active = true;

    protected function rules(): array
    {
        return [
            'email' => 'required|email',
            'frequency' => 'required|in:' . implode(',', array_keys(NotificationSchedule::FREQUENCIES)),
            'send_at' => 'required',
            'active' => 'boolean',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = NotificationSchedule::findOrFail($id);
        $this->editingId = $s->id;
        $this->email = $s->email;
        $this->frequency = $s->frequency;
        $this->send_at = substr($s->send_at, 0, 5);
        $this->active = $s->active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            NotificationSchedule::findOrFail($this->editingId)->update($data);
            session()->flash('schedule_message', 'Schedule updated.');
        } else {
            NotificationSchedule::create($data);
            session()->flash('schedule_message', 'Schedule created.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        NotificationSchedule::findOrFail($id)->delete();
        session()->flash('schedule_message', 'Schedule deleted.');
    }

    public function sendNow(int $id): void
    {
        $s = NotificationSchedule::findOrFail($id);
        Mail::to($s->email)->send(new InventorySummaryMail());
        $s->update(['last_sent_at' => now()]);
        session()->flash('schedule_message', "Summary sent to {$s->email} (check storage/logs/laravel.log if MAIL_MAILER=log).");
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->reset(['email', 'frequency', 'send_at', 'active']);
        $this->frequency = 'daily';
        $this->send_at = '08:00';
        $this->active = true;
        $this->resetValidation();
    }

    #[Computed]
    public function schedules()
    {
        return NotificationSchedule::orderBy('email')->get();
    }
}; ?>

<div class="space-y-6">
    @if (session('schedule_message'))
        <div class="rounded-md bg-green-50 border border-green-200 px-4 py-2 text-green-800 text-sm">
            {{ session('schedule_message') }}
        </div>
    @endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Configure recipients to receive an inventory summary email on a schedule.
            Run <code class="bg-gray-100 px-1 rounded">php artisan schedule:work</code> locally to dispatch them.
        </p>
        <button wire:click="create"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-md shadow">
            + New Schedule
        </button>
    </div>

    @if ($showForm)
        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">
                {{ $editingId ? 'Edit Schedule' : 'New Schedule' }}
            </h3>
            <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">Email *</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                    @error('email') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium">Frequency *</label>
                    <select wire:model="frequency" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                        @foreach (\App\Models\NotificationSchedule::FREQUENCIES as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Send at (HH:MM, server time) *</label>
                    <input type="time" wire:model="send_at" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 sm:text-sm">
                </div>
                <div class="flex items-center mt-6">
                    <label class="inline-flex items-center text-sm">
                        <input type="checkbox" wire:model="active" class="rounded border-gray-300 text-indigo-600">
                        <span class="ml-2">Active</span>
                    </label>
                </div>
                <div class="md:col-span-2 flex gap-2 justify-end">
                    <button type="button" wire:click="cancel"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                        {{ $editingId ? 'Update' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-700">Email</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-700">Frequency</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-700">Send At</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-700">Active</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-700">Last Sent</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->schedules as $s)
                    <tr>
                        <td class="px-4 py-2">{{ $s->email }}</td>
                        <td class="px-4 py-2">{{ \App\Models\NotificationSchedule::FREQUENCIES[$s->frequency] ?? $s->frequency }}</td>
                        <td class="px-4 py-2">{{ substr($s->send_at, 0, 5) }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs
                                {{ $s->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                {{ $s->active ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-600">
                            {{ $s->last_sent_at ? $s->last_sent_at->diffForHumans() : '—' }}
                        </td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <button wire:click="sendNow({{ $s->id }})"
                                    class="text-emerald-600 hover:underline text-sm">Send now</button>
                            <button wire:click="edit({{ $s->id }})"
                                    class="text-indigo-600 hover:underline text-sm">Edit</button>
                            <button wire:click="delete({{ $s->id }})"
                                    wire:confirm="Delete this schedule?"
                                    class="text-red-600 hover:underline text-sm">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                            No schedules configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
