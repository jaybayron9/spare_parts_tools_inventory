<?php

namespace Tests\Feature\Notifications;

use App\Models\EmailLog;
use App\Models\NotificationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchedule(array $attrs = []): NotificationSchedule
    {
        return NotificationSchedule::create(array_merge([
            'email' => 'test@example.com',
            'frequency' => 'daily',
            'send_at' => '08:00',
            'active' => true,
        ], $attrs));
    }

    public function test_send_now_writes_email_log(): void
    {
        Mail::fake();
        $schedule = $this->makeSchedule();

        Livewire::test('notification-schedule-manager')
            ->call('sendNow', $schedule->id);

        $this->assertDatabaseHas('email_logs', [
            'notification_schedule_id' => $schedule->id,
            'email' => $schedule->email,
        ]);
        $this->assertSame(1, EmailLog::count());
    }

    public function test_send_now_updates_last_sent_at(): void
    {
        Mail::fake();
        $schedule = $this->makeSchedule();
        $this->assertNull($schedule->last_sent_at);

        Livewire::test('notification-schedule-manager')
            ->call('sendNow', $schedule->id);

        $this->assertNotNull($schedule->fresh()->last_sent_at);
    }

    public function test_history_panel_shows_logs_for_schedule(): void
    {
        $schedule = $this->makeSchedule();
        EmailLog::create([
            'notification_schedule_id' => $schedule->id,
            'email' => $schedule->email,
            'sent_at' => now()->subHour(),
        ]);

        Livewire::test('notification-schedule-manager')
            ->call('showHistory', $schedule->id)
            ->assertSet('historyId', $schedule->id);
    }

    public function test_history_panel_toggles_off_when_called_again(): void
    {
        $schedule = $this->makeSchedule();

        Livewire::test('notification-schedule-manager')
            ->call('showHistory', $schedule->id)
            ->assertSet('historyId', $schedule->id)
            ->call('showHistory', $schedule->id)
            ->assertSet('historyId', null);
    }

    public function test_history_capped_at_20_entries(): void
    {
        $schedule = $this->makeSchedule();

        for ($i = 0; $i < 25; $i++) {
            EmailLog::create([
                'notification_schedule_id' => $schedule->id,
                'email' => $schedule->email,
                'sent_at' => now()->subMinutes($i),
            ]);
        }

        $component = Livewire::test('notification-schedule-manager')
            ->call('showHistory', $schedule->id);

        $history = $component->get('history');
        $this->assertCount(20, $history);
    }

    public function test_schedules_computed_includes_email_logs_count(): void
    {
        $schedule = $this->makeSchedule();
        EmailLog::create([
            'notification_schedule_id' => $schedule->id,
            'email' => $schedule->email,
            'sent_at' => now(),
        ]);

        $component = Livewire::test('notification-schedule-manager');
        $schedules = $component->get('schedules');

        $this->assertSame(1, $schedules->first()->email_logs_count);
    }
}
