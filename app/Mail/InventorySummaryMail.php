<?php

namespace App\Mail;

use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InventorySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Inventory Summary — '.now()->format('Y-m-d'),
        );
    }

    public function content(): Content
    {
        $items = Item::with('itemType')->orderBy('name')->get();

        $sparePartId = ItemType::where('label', 'Spare Part')->value('id');
        $toolId = ItemType::where('label', 'Tool')->value('id');

        return new Content(
            markdown: 'mail.inventory-summary',
            with: [
                'totalItems' => $items->count(),
                'totalSpareParts' => $items->where('item_type_id', $sparePartId)->count(),
                'totalTools' => $items->where('item_type_id', $toolId)->count(),
                'lowStockItems' => $items->filter(fn ($i) => $i->is_low_stock)->values(),
                'totalValue' => $items->sum(fn ($i) => $i->quantity * (float) $i->unit_price),
                'items' => $items,
            ],
        );
    }
}
