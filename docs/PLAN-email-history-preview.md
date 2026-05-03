# PLAN: Email History & Template Preview

**Slug:** `email-history-preview`
**Mode:** PLANNING (no code yet)
**Project Type:** WEB (Laravel 13 + Livewire 4 + Tailwind 4)
**Created:** 2026-05-03

---

## Overview

Two additions to the Notifications page:

1. **Email Send History** — every call to `sendNow()` or the scheduled `SendInventorySummaries` command records a lightweight log row (recipient + timestamp). The notification manager table gains a "History" action per schedule that shows a chronological list of sends.

2. **Email Template Preview** — a collapsible panel on the notifications page renders the actual `InventorySummaryMail` Blade template in an `<iframe>` via a dedicated controller endpoint (`GET /mail-preview`). Uses live DB data so the preview always reflects the current inventory state.

---

## Decisions (from Socratic Gate)

| Question | Decision |
|---|---|
| History fields | **Recipient email + timestamp** (minimal — no trigger type, no snapshot counts, no status) |
| Preview UI | **Inline iframe** — collapsible panel on the notifications page via a `GET /mail-preview` endpoint |

---

## Current State

- `NotificationSchedule` model has `last_sent_at` (single datetime, overwritten each send) — no full log.
- `sendNow(int $id)` in `⚡notification-schedule-manager.blade.php` sends mail + updates `last_sent_at`.
- `SendInventorySummaries` Artisan command iterates schedules and sends — also updates `last_sent_at`.
- `InventorySummaryMail` is a Markdown mailable; template at `resources/views/mail/inventory-summary.blade.php`.

---

## Success Criteria

1. Every `sendNow()` call and every scheduled dispatch writes a row to `email_logs` (schedule FK + email + `sent_at`).
2. The notification manager shows a "History (N)" link per schedule; clicking it expands an inline list of past sends (most recent first, capped at 20 rows for now).
3. `GET /mail-preview` returns the fully-rendered HTML of `InventorySummaryMail` using live data.
4. A collapsible "Preview email" panel on the notifications page embeds `/mail-preview` in an `<iframe>`.
5. No existing tests break. New feature tests cover the log write and the preview endpoint.

---

## Affected Files

| File | Change |
|---|---|
| `database/migrations/XXXX_create_email_logs_table.php` | NEW |
| `app/Models/EmailLog.php` | NEW |
| `app/Models/NotificationSchedule.php` | Add `hasMany(EmailLog)` |
| `app/Console/Commands/SendInventorySummaries.php` | Log on dispatch |
| `resources/views/components/⚡notification-schedule-manager.blade.php` | Log on `sendNow()`, history UI, preview panel |
| `app/Http/Controllers/MailPreviewController.php` | NEW |
| `routes/web.php` | Add `GET /mail-preview` |
| `tests/Feature/Notifications/EmailLogTest.php` | NEW |
| `tests/Feature/Notifications/MailPreviewTest.php` | NEW |

---

## Task Breakdown

### T1 — Migration + `EmailLog` model

- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P0
- **Dependencies:** none
- **INPUT:** schema decision (recipient email + timestamp + FK)
- **OUTPUT:**
  - Migration: `email_logs` table with `id`, `notification_schedule_id` (unsignedBigInt, FK → `notification_schedules.id`, `onDelete('set null')`, nullable), `email` (varchar), `sent_at` (datetime). Index on `(notification_schedule_id, sent_at)`.
  - `app/Models/EmailLog.php` with `$fillable = ['notification_schedule_id', 'email', 'sent_at']`, cast `sent_at => 'datetime'`, `belongsTo(NotificationSchedule)`.
  - `NotificationSchedule::emailLogs()` → `hasMany(EmailLog)`.
- **VERIFY:** `php artisan migrate` succeeds; `php artisan migrate:rollback` reverses.

### T2 — Log on `sendNow()` (notification manager SFC)

- **Agent / Skill:** `backend-specialist` / `livewire-development`
- **Priority:** P0
- **Dependencies:** T1
- **INPUT:** `sendNow(int $id)` in `⚡notification-schedule-manager.blade.php`
- **OUTPUT:** After `Mail::to($s->email)->send(...)`, insert `EmailLog::create(['notification_schedule_id' => $s->id, 'email' => $s->email, 'sent_at' => now()])`.
- **VERIFY:** Call `sendNow()` in test — assert `email_logs` row exists with correct email + schedule FK.

### T3 — Log on scheduled dispatch

- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P0
- **Dependencies:** T1
- **INPUT:** `app/Console/Commands/SendInventorySummaries.php`
- **OUTPUT:** Same `EmailLog::create(...)` call after each successful `Mail::to()->send()` in the command.
- **VERIFY:** Test the command (or inspect existing test) to assert log row written.

### T4 — History UI in notification manager

- **Agent / Skill:** `frontend-specialist` / `livewire-development`
- **Priority:** P1
- **Dependencies:** T2
- **INPUT:** `EmailLog` relationship on `NotificationSchedule`
- **OUTPUT:**
  - New Livewire property `public ?int $historyId = null` — stores the schedule ID whose history is expanded.
  - `showHistory(int $id): void` — sets `$historyId = $id` (toggles off if same).
  - `#[Computed] public function history()` — returns `EmailLog::where('notification_schedule_id', $this->historyId)->latest('sent_at')->limit(20)->get()` when `$historyId` is set, else `null`.
  - In the Actions column: "History ({{ $s->emailLogs()->count() }})" button (or `0` when none). Add `wire:loading.attr="disabled"` + `wire:target="showHistory"` spinner.
  - Below the schedules table: a collapsible panel (`@if ($historyId)`) showing a simple list — date/time + email — for the active schedule, with a "Close" link.
- **VERIFY:** Click "History (N)" → panel appears with ≤ 20 rows, most recent first; clicking same button again collapses.

### T5 — `MailPreviewController` + route

- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P1
- **Dependencies:** none (parallel to T1–T4)
- **INPUT:** `InventorySummaryMail`
- **OUTPUT:**
  - `app/Http/Controllers/MailPreviewController.php` — single `__invoke` method; builds `InventorySummaryMail`, calls `$mailable->render()`, returns `response($html)` with `Content-Type: text/html`.
  - `routes/web.php`: `Route::get('/mail-preview', MailPreviewController::class)->name('mail.preview')`.
- **VERIFY:** `GET /mail-preview` returns 200 with rendered HTML containing "Inventory Summary".

### T6 — Preview panel in notification manager

- **Agent / Skill:** `frontend-specialist` / `livewire-development`
- **Priority:** P1
- **Dependencies:** T5
- **INPUT:** `/mail-preview` route
- **OUTPUT:**
  - New Livewire property `public bool $showPreview = false`.
  - `togglePreview(): void` — flips `$showPreview`.
  - A "Preview email template" button (below/above the schedules table) that calls `togglePreview`.
  - When `$showPreview`: render `<iframe src="{{ route('mail.preview') }}" class="w-full h-[600px] rounded border border-gray-200">`. Wrap in a collapsible `<div>` with a header bar and "Close" button.
- **VERIFY:** Click "Preview email template" → iframe loads and renders the inventory summary mail; "Close" collapses it.

### T7 — Feature tests

- **Agent / Skill:** `test-engineer` / `laravel-best-practices`
- **Priority:** P2
- **Dependencies:** T2, T5
- **INPUT:** `Livewire::test`, `$this->get('/mail-preview')`
- **OUTPUT:**
  - `tests/Feature/Notifications/EmailLogTest.php`:
    1. `test_send_now_writes_email_log()` — call `sendNow()` via Livewire test, assert 1 row in `email_logs` with correct email.
    2. `test_history_panel_shows_logs_for_schedule()` — create 3 log rows, call `showHistory($id)`, assert computed `history` count = 3.
    3. `test_history_capped_at_20()` — create 25 log rows, assert history returns 20.
  - `tests/Feature/Notifications/MailPreviewTest.php`:
    1. `test_mail_preview_returns_200_with_html()` — `$this->get('/mail-preview')->assertOk()->assertSee('Inventory Summary')`.
- **VERIFY:** `php artisan test --compact --filter=EmailLog|MailPreview` all green.

### T8 — Pint

- **Agent / Skill:** `backend-specialist`
- **Priority:** P2
- **Dependencies:** T1–T7
- **OUTPUT:** `vendor/bin/pint --dirty --format agent` clean on all changed files.

---

## Dependency Graph

```
T1 ──► T2 ──► T4 ──► T6 ──► T7 ──► T8
       T3 ─────────────────────────┘
T5 ──► T6
```

T5 (preview controller) is fully independent of T1–T4 and can be developed in parallel.

---

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| `InventorySummaryMail::render()` queries DB — preview could be slow with 279 items | L | L | Acceptable; adds `<1 s`. No caching needed at this scale. |
| `email_logs` grows unboundedly | L | L | Cap history display at 20; can add a prune job later if needed. |
| iframe preview blocked by X-Frame-Options | L | M | The app controls its own headers — no external CSP to fight. Add `->withHeaders(['X-Frame-Options' => 'SAMEORIGIN'])` in the controller if needed. |
| `SendInventorySummaries` command test doesn't exist yet | M | L | Write a focused test in T7 or note it as a follow-on. |

---

## Out of Scope

- Storing the full rendered email body in the log.
- Email open/click tracking.
- Pagination of history (>20 rows).
- Authentication/gate on `/mail-preview` (internal tool, no auth layer yet in this app).
- Trigger type (manual vs scheduled) in the log.

---

## Phase X: Verification Checklist

- [ ] `php artisan migrate` succeeds; rollback reverses
- [ ] `php artisan test --compact --filter=EmailLog` — all green
- [ ] `php artisan test --compact --filter=MailPreview` — all green
- [ ] `php artisan test --compact` — full suite (excluding pre-existing ExampleTest failure)
- [ ] `vendor/bin/pint --test --format agent` on modified files — exit 0
- [ ] Manual: click "Send now" → History count increments
- [ ] Manual: click "History (N)" → panel shows sends, most recent first
- [ ] Manual: click "Preview email template" → iframe renders inventory mail with real data
- [ ] Manual: close preview → collapses cleanly
- [ ] No errors in `storage/logs/laravel.log`

### Phase X Completion Marker

```
## ✅ PHASE X COMPLETE
- Migration: ✅
- Log on send: ✅
- History UI: ✅
- Preview endpoint: ✅
- Tests: ✅
- Pint: ✅
- Date: 2026-__-__
```
