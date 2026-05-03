@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <h2 class="text-2xl font-semibold">Inventory</h2>
        <p class="text-sm text-gray-600">Manage spare parts and tools — create, edit, sort, and reorder columns.</p>
    </div>
    <div class="space-y-6">
        <livewire:import-items />
        <livewire:item-manager />
    </div>
@endsection
