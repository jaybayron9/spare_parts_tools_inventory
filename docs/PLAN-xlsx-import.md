# PLAN: XLSX Import for Spare Parts Inventory

**Slug:** `xlsx-import`
**Mode:** PLANNING (no code yet)
**Project Type:** WEB (Laravel 13 + Livewire 4 + Tailwind 4)
**Source File:** `example.xlsx` (project root)
**Created:** 2026-04-28

---

## Overview

Add an "Import XLSX" feature to the existing Items management page so the user can upload a spare-parts spreadsheet (the same shape as `example.xlsx`) and bulk-load it into the `items` table. Import previews rows before commit and **upserts by `sku`** so re-importing an updated sheet refreshes existing rows instead of duplicating them.

### Why
The user maintains a master spare-parts spreadsheet (18 columns including Vendor, Brand, Equipment/System, Critical Spare flag, UOM, Leadtime, Service Life, EUL, etc.). Today, the `items` table only stores 9 of those fields, so even manual data entry loses information. We extend the schema to capture the full sheet, then automate ingestion.

---

## Decisions (from Socratic Gate)

| Question | Decision |
|---|---|
| Schema scope | **Extend `items` schema** with all xlsx fields |
| Where the UI lives | **Livewire upload on the existing Items page** |
| Duplicate handling | **Upsert by `sku`** (existing rows updated, missing inserted) |

---

## Source File Analysis (`example.xlsx`)

Header row maps to these columns (sheet1, row 2; row 1 is a banner: "CRITICAL SPARE PARTS INVENTORY"):

| # | XLSX Header | Target column | Type | Notes |
|---|-------------|---------------|------|-------|
| A | SPARE PARTS DESCRIPTION | `name` | string | required |
| B | Vendor | `vendor` | string, nullable | NEW |
| C | Equipment/System | `equipment_system` | string, nullable | NEW (also drives `category`) |
| D | Contract | `contract` | string, nullable | NEW (e.g. "OFCI") |
| E | Brand | `brand` | string, nullable | NEW |
| F | Part Number | `sku` | string, indexed, unique upsert key | already exists |
| G | Installation and Programming remarks | `install_remarks` | text, nullable | NEW |
| H | Critical Spare (Yes or No) | `is_critical` | boolean | NEW (Yes→true) |
| I | UOM | `uom` | string(16), nullable | NEW (Pcs, Set, etc.) |
| J | PARTS QUANTITY FOR REGULAR STOCKING | `quantity` | integer | parse "On stock c/o Trane"-style strings → null + capture in remarks |
| K | Leadtime | `leadtime` | string, nullable | NEW (free-form, e.g. "12 weeks") |
| L | MIN. QTY. TO TRIGER REPLENISHMENT | `reorder_level` | integer | already exists |
| M | STORAGE FACILITY | `location` | string | already exists (e.g. "STT FV") |
| N | DATE PURCHASED | `date_purchased` | date, nullable | NEW (parse "TBC" → null) |
| O | PARTS SERVICE LIFE (YRS) | `service_life_yrs` | smallint, nullable | NEW |
| P | PARTS EUL (YR) | `eul_yrs` | smallint, nullable | NEW |
| Q | FREQUENCY OF REPLACEMENT | `replacement_frequency` | string, nullable | NEW (e.g. "As needed") |
| R | REMARKS | `notes` | text | reuse existing column |

Existing-but-not-mapped columns kept as-is: `type`, `category`, `unit_price`. (`category` is auto-populated from `equipment_system` if blank.)

---

## Success Criteria

1. User clicks "Import XLSX" on the Items page, selects `example.xlsx`, sees a **preview table** of parsed rows + per-row validation status, and clicks **Confirm** to commit.
2. After confirm, the `items` table contains every data row from the file. Re-running the import on the same file yields **0 inserts, N updates, 0 duplicates**.
3. Rows with invalid/blank `Part Number` are flagged in the preview and **skipped** on commit (with reason); commit never partially writes — wrapped in a DB transaction.
4. All 18 source columns are persisted (subject to mapping table above). No data is silently dropped except non-numeric strings in numeric fields, which are captured into `notes` with a prefix `[import] …`.
5. Feature works against the included `example.xlsx` end-to-end with zero manual schema work.
6. Feature tests cover: happy path, upsert path, malformed-file rejection, header-mismatch rejection, blank-SKU skip.

---

## Tech Stack & Rationale

| Choice | Why |
|---|---|
| **`openspout/openspout`** for xlsx parsing | Pure-PHP, low memory (streams rows), MIT, no PhpSpreadsheet weight. Handles `.xlsx` only — fine for our needs. Composer-installable, no system deps. |
| **Livewire 4 file upload** (`WithFileUploads`) | Already the project's reactive layer; no new JS. `temporaryUrl()` keeps the file in `storage/app/livewire-tmp/` between preview and confirm. |
| **Form Request / Livewire validation** | Validate file mime, size cap (5 MB), and header row before any DB work. |
| **`Item::upsert()`** keyed on `sku` | One-statement bulk upsert; SQLite + MySQL supported. |
| **Tailwind 4** | Existing styling system; reuse table/button utilities from `⚡item-manager.blade.php`. |
| **PHPUnit 12 feature tests** | Project standard per CLAUDE.md. |

> Alternative considered: `maatwebsite/excel`. Rejected — heavier, opinionated, and we don't need its export/queue/styling features.

---

## File Structure (additions)

```
app/
  Livewire/
    Inventory/
      ImportItems.php              ← NEW: SFC class side
  Imports/
    ItemsXlsxParser.php            ← NEW: pure parser (file → rows[])
    ItemRowMapper.php              ← NEW: xlsx row → Item attributes
  Models/
    Item.php                       ← MODIFY: add new $fillable + casts
database/
  migrations/
    2026_04_28_000000_extend_items_for_xlsx_import.php   ← NEW
resources/
  views/
    components/
      ⚡item-manager.blade.php     ← MODIFY: mount <livewire:inventory.import-items>
    livewire/
      inventory/
        import-items.blade.php     ← NEW: upload, preview, confirm UI
tests/
  Feature/
    Inventory/
      ImportItemsTest.php          ← NEW
  Unit/
    Imports/
      ItemRowMapperTest.php        ← NEW
docs/
  PLAN-xlsx-import.md              ← THIS FILE
example.xlsx                        ← reference fixture (copy into tests/Fixtures/)
```

---

## Task Breakdown

> Each task: `INPUT → OUTPUT → VERIFY`. Tasks ≤ 10 minutes each. Dependencies are explicit.

### T1 — Add openspout dependency
- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P0
- **Dependencies:** none
- **INPUT:** `composer.json`
- **OUTPUT:** `openspout/openspout ^4` in `require`; `composer install` succeeds
- **VERIFY:** `composer show openspout/openspout` returns version; `php artisan about` boots cleanly
- **Rollback:** `composer remove openspout/openspout`

### T2 — Migration: extend `items` table
- **Agent / Skill:** `database-architect` / `laravel-best-practices`
- **Priority:** P0
- **Dependencies:** T1
- **INPUT:** schema decisions table above
- **OUTPUT:** `2026_04_28_000000_extend_items_for_xlsx_import.php` adding columns: `vendor`, `brand`, `equipment_system`, `contract`, `is_critical (bool, default false)`, `uom`, `install_remarks (text)`, `leadtime`, `date_purchased (date)`, `service_life_yrs (unsignedSmallInt)`, `eul_yrs (unsignedSmallInt)`, `replacement_frequency`. All new columns nullable. Add unique index on `sku` (currently not unique — required for upsert).
- **VERIFY:**
  - `php artisan migrate` succeeds.
  - `php artisan migrate:rollback` reverses cleanly.
  - `database-schema` MCP shows new columns + unique index.
  - Pre-existing rows are unaffected.
- **Rollback:** rollback migration.
- **Risk:** existing `sku` column may have duplicate/null values. **Mitigation:** dry-run `SELECT sku, COUNT(*) FROM items GROUP BY sku HAVING COUNT(*)>1 OR sku IS NULL;` before adding the unique index; if dirty, add a one-shot data-cleanup step in the migration's `up()` (null skus → `'IMPORTED-' || id`).

### T3 — Update `Item` model
- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P0
- **Dependencies:** T2
- **INPUT:** new columns from T2
- **OUTPUT:** `app/Models/Item.php` `$fillable` extended; `$casts` adds `is_critical => bool`, `date_purchased => 'date'`, `service_life_yrs => 'integer'`, `eul_yrs => 'integer'`.
- **VERIFY:** `php artisan tinker --execute 'App\Models\Item::factory()->make()->toArray();'` returns array containing new keys (factory updated in T7).

### T4 — `ItemsXlsxParser` (pure PHP, no Livewire)
- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P1
- **Dependencies:** T1
- **INPUT:** absolute path to a `.xlsx`
- **OUTPUT:** `app/Imports/ItemsXlsxParser.php`. Reads sheet 1 with openspout's `Reader\XLSX`. Skips banner row 1, treats row 2 as header, validates header set against expected map, yields associative rows. Throws `InvalidXlsxException` on bad header.
- **VERIFY:** unit-style assertion in T8 that parsing the bundled `example.xlsx` returns ≥ 1 row whose `Part Number` is `SEN02133`.

### T5 — `ItemRowMapper` (xlsx row → Item attribute array)
- **Agent / Skill:** `backend-specialist` / `laravel-best-practices`
- **Priority:** P1
- **Dependencies:** T3, T4
- **INPUT:** raw row array from parser
- **OUTPUT:** `app/Imports/ItemRowMapper.php` with `map(array $row): array` returning Item-fillable array. Handles:
  - "Yes"/"No" → bool for `is_critical`.
  - Non-numeric `quantity` (e.g., "On stock c/o Trane") → `quantity = 0`, prefix the string into `notes`.
  - "TBC", "" → null for `date_purchased`.
  - Auto-fill `category` from `equipment_system` when blank.
  - Trim whitespace on every string; collapse double spaces.
- **VERIFY:** `tests/Unit/Imports/ItemRowMapperTest.php` covers each branch.

### T6 — Livewire SFC `Inventory\ImportItems`
- **Agent / Skill:** `frontend-specialist` (with `livewire-development` + `tailwindcss-development` skills)
- **Priority:** P1
- **Dependencies:** T4, T5
- **INPUT:** mapped rows from T5
- **OUTPUT:**
  - `app/Livewire/Inventory/ImportItems.php` — properties: `?TemporaryUploadedFile $file`, `array $preview = []`, `array $errors = []`, `bool $confirming = false`. Methods: `updatedFile()` (parses → fills preview + errors), `confirm()` (DB transaction, `Item::upsert($valid, ['sku'], [...all-cols-except-sku-and-timestamps])`, then `$this->reset()` and dispatches `items-imported` event), `cancel()`.
  - `resources/views/livewire/inventory/import-items.blade.php` — Tailwind upload card → preview table (sticky header, scroll, row error badges) → "Confirm import (N rows)" button. Dark-mode classes parallel to existing items table.
  - Validation: `mimes:xlsx`, `max:5120` (KB).
- **VERIFY:** rendered manually via `npm run dev` + browser; `wire:model` binding works; preview clears after confirm.

### T7 — Mount component on items page + factory update
- **Agent / Skill:** `frontend-specialist` / `livewire-development`
- **Priority:** P1
- **Dependencies:** T6
- **INPUT:** existing `resources/views/components/⚡item-manager.blade.php`
- **OUTPUT:**
  - Add an "Import XLSX" button + `<livewire:inventory.import-items />` mount on the items index, listen for `items-imported` to refresh the items list.
  - Update `database/factories/ItemFactory.php` (if present; create if not) to provide values for the new columns.
- **VERIFY:** visiting `/` shows the new button; clicking it reveals upload UI; refresh after import shows new rows.

### T8 — Feature + unit tests
- **Agent / Skill:** `test-engineer` / `laravel-best-practices`
- **Priority:** P2
- **Dependencies:** T6
- **INPUT:** `example.xlsx` copied to `tests/Fixtures/example.xlsx`
- **OUTPUT:**
  - `tests/Unit/Imports/ItemRowMapperTest.php` — 6+ assertions across mapper branches.
  - `tests/Feature/Inventory/ImportItemsTest.php` — uses Livewire test helper:
    1. `test_upload_previews_rows()` — uploads fixture, asserts preview count > 0.
    2. `test_confirm_inserts_rows()` — asserts `items` row count grows by parsed-row count.
    3. `test_re_import_upserts_not_duplicates()` — runs import twice, asserts no growth on second.
    4. `test_blank_sku_rows_are_skipped_with_reason()`.
    5. `test_invalid_mime_rejected()`.
    6. `test_header_mismatch_throws_validation()`.
- **VERIFY:** `php artisan test --compact --filter=Import` is all green.

### T9 — Pint + final polish
- **Agent / Skill:** `backend-specialist`
- **Priority:** P2
- **Dependencies:** T1–T8
- **OUTPUT:** `vendor/bin/pint --dirty --format agent` runs clean; remove any TODOs.
- **VERIFY:** `vendor/bin/pint --test --format agent` (only this once, for final sign-off) — exit 0.

---

## Dependency Graph

```
T1 ──► T2 ──► T3 ──► T5 ──► T6 ──► T7 ──► T8 ──► T9
   └──► T4 ──┘
```

T1 unblocks T2 and T4 in parallel. T6/T7 can begin once T5 is done. T8 waits on T6.

---

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| `items.sku` already has duplicates/nulls — unique index will fail | M | H | Inspect with `database-query` before T2; bake cleanup into migration `up()` |
| Spreadsheet header drift (user adds/renames columns) | M | M | Header validation in `ItemsXlsxParser` with explicit mismatch error in preview |
| Livewire 4 file upload size on shared hosting | L | M | Hard-cap at 5 MB; surface `php.ini upload_max_filesize` mismatch in error UI |
| Quantity column has non-numeric prose ("On stock c/o Trane") | H (already in fixture) | L | Mapper handles by `0` + note prefix; documented in T5 |
| Re-importing replaces hand-edited fields (e.g. `unit_price`) | M | M | `upsert()` only writes columns derived from the xlsx — `unit_price`, `type` excluded from the update list |

---

## Out of Scope

- Export to xlsx (read-only feature for now).
- Queue/async import (5 MB / ~2k rows runs sync in <5 s; can revisit when needed).
- Multi-sheet workbooks.
- Image/photo columns.
- An import history / audit log table.
- Mapping editor UI (header → column mapping is hard-coded).

---

## Phase X: Verification Checklist

Run **after** implementation — do not pre-tick.

- [ ] `php artisan migrate` runs cleanly on a fresh DB
- [ ] `php artisan migrate:rollback` reverses cleanly
- [ ] `composer show openspout/openspout` lists the package
- [ ] `php artisan test --compact --filter=Import` — all green
- [ ] `php artisan test --compact` — full suite still green (no regressions)
- [ ] `vendor/bin/pint --test --format agent` — exit 0
- [ ] `npm run build` — no errors
- [ ] Manual: visit `/`, click "Import XLSX", upload `example.xlsx`, see preview, confirm — items table reflects rows
- [ ] Manual: re-upload same file — confirm message reads "0 inserted, N updated"
- [ ] Manual: blank-SKU row appears in preview with a clear "skipped" badge
- [ ] No Laravel error in `storage/logs/laravel.log` after the manual run
- [ ] No browser-console errors (`browser-logs` MCP shows clean run)

### Phase X Completion Marker (fill on completion)

```
## ✅ PHASE X COMPLETE
- Migration: ✅
- Tests: ✅
- Pint: ✅
- Build: ✅
- Manual run: ✅
- Date: 2026-__-__
```

---

## Open Questions for Implementation Phase

None right now. If the live `items` table holds duplicate `sku`s, T2 must surface the cleanup strategy to the user before proceeding.
