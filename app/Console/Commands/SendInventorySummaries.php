<?php

namespace App\Console\Commands;

use App\Mail\InventorySummaryMail;
use App\Models\NotificationSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('inventory:send-summaries')]
#[Description('Send the inventory summary email to every active schedule that is due now.')]
class SendInventorySummaries extends Command
{
    public function handle(): int
    {
        $now = now();
        $sent = 0;

        NotificationSchedule::where('active', true)->get()->each(function ($schedule) use ($now, &$sent) {
            if (! $schedule->isDueAt($now)) {
                return;
            }

            Mail::to($schedule->email)->send(new InventorySummaryMail());
            $schedule->update(['last_sent_at' => $now]);
            $this->info("Sent summary to {$schedule->email} ({$schedule->frequency}).");
            $sent++;
        });

        $this->line("Done. Sent {$sent} summary email(s).");

        return self::SUCCESS;
    }
}
