<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Spare Parts & Tools Inventory' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-gray-50 min-h-screen text-gray-900">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <h1 class="text-xl font-bold">Spare Parts &amp; Tools Inventory</h1>
            <nav class="flex gap-4 text-sm">
                <a href="{{ route('inventory.index') }}"
                   class="{{ request()->routeIs('inventory.index') ? 'text-indigo-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                    Inventory
                </a>
                <a href="{{ route('inventory.notifications') }}"
                   class="{{ request()->routeIs('inventory.notifications') ? 'text-indigo-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                    Email Schedules
                </a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
