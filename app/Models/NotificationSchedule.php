<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    use HasFactory;

    public const FREQUENCIES = [
        'daily' => 'Daily',
        'weekly' => 'Weekly (every Monday)',
        'monthly' => 'Monthly (1st of the month)',
    ];

    protected $fillable = [
        'email',
        'frequency',
        'send_at',
        'active',
        'last_sent_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function isDueAt(\DateTimeInterface $now): bool
    {
        if (! $this->active) {
            return false;
        }

        $sendAt = \Carbon\Carbon::parse($this->send_at);
        $nowCarbon = \Carbon\Carbon::instance($now);

        if ($nowCarbon->format('H:i') !== $sendAt->format('H:i')) {
            return false;
        }

        return match ($this->frequency) {
            'daily' => true,
            'weekly' => $nowCarbon->isMonday(),
            'monthly' => $nowCarbon->day === 1,
            default => false,
        };
    }
}
