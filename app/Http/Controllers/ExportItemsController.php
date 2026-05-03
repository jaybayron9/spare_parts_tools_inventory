<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportItemsController extends Controller
{
    private const HEADERS = [
        'SKU', 'Name', 'Type', 'Category', 'Quantity', 'Reorder Level',
        'Unit Price', 'Location', 'Vendor', 'Brand', 'Equipment System',
        'Contract', 'Is Critical', 'UOM', 'Leadtime', 'Date Purchased',
        'Service Life (yrs)', 'EUL (yrs)', 'Replacement Frequency', 'Notes',
    ];

    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $items = $this->resolveItems($request);

        $tmpFile = tempnam(sys_get_temp_dir(), 'inv_export_').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($tmpFile);
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($items as $item) {
            $writer->addRow(Row::fromValues([
                $item->sku,
                $item->name,
                $item->itemType?->label ?? '',
                $item->category ?? '',
                $item->quantity,
                $item->reorder_level,
                (float) $item->unit_price,
                $item->location ?? '',
                $item->vendor ?? '',
                $item->brand ?? '',
                $item->equipment_system ?? '',
                $item->contract ?? '',
                $item->is_critical ? 'Yes' : 'No',
                $item->uom ?? '',
                $item->leadtime ?? '',
                $item->date_purchased?->toDateString() ?? '',
                $item->service_life_yrs ?? '',
                $item->eul_yrs ?? '',
                $item->replacement_frequency ?? '',
                $item->notes ?? '',
            ]));
        }

        $writer->close();

        $filename = 'inventory-export-'.now()->toDateString().'.xlsx';

        return response()->download($tmpFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function resolveItems(Request $request): Collection
    {
        if ($request->filled('ids')) {
            $ids = array_filter(explode(',', $request->string('ids')->toString()));

            return Item::with('itemType')
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get();
        }

        $search = $request->string('search')->toString();
        $filterType = $request->string('filter_type')->toString();

        return Item::with('itemType')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            }))
            ->when($filterType, fn ($q) => $q->where('item_type_id', $filterType))
            ->orderBy('name')
            ->get();
    }
}
