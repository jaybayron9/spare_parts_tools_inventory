@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <h2 class="text-2xl font-semibold">Email Schedules</h2>
        <p class="text-sm text-gray-600">Schedule recurring inventory summary emails.</p>
    </div>
    <livewire:notification-schedule-manager />
@endsection
