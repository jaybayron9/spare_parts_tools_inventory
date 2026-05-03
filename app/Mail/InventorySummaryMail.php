<?php

namespace App\Mail;

use App\Models\Item;
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
            subject: 'Inventory Summary — ' . now()->format('Y-m-d'),
        );
    }

    public function content(): Content
    {
        $items = Item::orderBy('type')->orderBy('name')->get();

        return new Content(
            markdown: 'mail.inventory-summary',
            with: [
                'totalItems' => $items->count(),
                'totalSpareParts' => $items->where('type', Item::TYPE_SPARE_PART)->count(),
                'totalTools' => $items->where('type', Item::TYPE_TOOL)->count(),
                'lowStockItems' => $items->filter(fn ($i) => $i->is_low_stock)->values(),
                'totalValue' => $items->sum(fn ($i) => $i->quantity * (float) $i->unit_price),
                'items' => $items,
            ],
        );
    }
}
