<x-mail::message>
# Inventory Summary

Here is the current state of the spare parts and tools inventory as of **{{ now()->format('F j, Y g:i A') }}**.

## Overview

| Metric | Value |
| :--- | ---: |
| Total items | {{ $totalItems }} |
| Spare parts | {{ $totalSpareParts }} |
| Tools | {{ $totalTools }} |
| Total stock value | {{ number_format($totalValue, 2) }} |
| Low-stock items | {{ $lowStockItems->count() }} |

@if ($lowStockItems->isNotEmpty())
## Low-stock items

<x-mail::table>
| Name | SKU | Type | Qty | Reorder |
| :--- | :--- | :--- | ---: | ---: |
@foreach ($lowStockItems as $item)
| {{ $item->name }} | {{ $item->sku }} | {{ $item->type_label }} | {{ $item->quantity }} | {{ $item->reorder_level }} |
@endforeach
</x-mail::table>
@endif

## All items

<x-mail::table>
| Name | SKU | Type | Category | Qty | Unit Price |
| :--- | :--- | :--- | :--- | ---: | ---: |
@foreach ($items as $item)
| {{ $item->name }} | {{ $item->sku }} | {{ $item->type_label }} | {{ $item->category ?? '—' }} | {{ $item->quantity }} | {{ number_format((float) $item->unit_price, 2) }} |
@endforeach
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
