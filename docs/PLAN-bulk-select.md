# PLAN: Bulk Select — Delete & Import

**Slug:** `bulk-select`
**Mode:** PLANNING (no code yet)
**Project Type:** WEB (Laravel 13 + Livewire 4 + Tailwind 4)
**Created:** 2026-05-03

---

## Overview

Two related bulk-selection features:

1. **Bulk Delete on Items table** — each row gets a checkbox; when ≥1 row is checked a "Delete selected (N)" button appears in the toolbar. A select-all checkbox in the header toggles the full current page.

2. **Selective Import in Preview** — each "ready" row in the import preview gets a checkbox (pre-checked); unchecked rows are excluded from the upsert on confirm. The "Confirm import (N rows)" button count updates live as rows are toggled.

### Why
Both features are listed in the same user request, share the checkbox pattern, and have no shared code — they can be developed in parallel after the pattern is agreed.

---

## Decisions (from Socratic Gate)

| Question | Decision |
|---|---|
| Bulk delete action location | **Toolbar button above table** — "Delete selected (N)" appears when ≥1 checked; select-all in header |
| Deselected import rows | **Skip only** — stay visible in preview, excluded from upsert; badge updates live |

---

## Success Criteria

### Bulk Delete
1. Each item row has a checkbox in a new leftmost column.
2. The table header has a "select all (current page)" checkbox that checks/unchecks all visible rows.
3. Checking ≥1 row makes a "Delete selected (N)" button appear in the toolbar; N updates as selection changes.
4. Clicking "Delete selected (N)" shows a confirm prompt, then deletes all selected items in one query and clears the selection.
5. Navigating to a new page clears the current-page selection (state is not cross-page).
6. The individual per-row Edit / Delete buttons remain unchanged.

### Selective Import
7. Every "ready" row in the import preview has a pre-checked checkbox.
8. "Skipped" rows (blank SKU etc.) have no checkbox — they are always excluded.
9. The "Confirm import (N rows)" button count reflects only checked ready-rows.
10. A "Select all / Deselect all" toggle exists above the preview table.
11. Unchecking a row keeps it visible with a dimmed style; it is not sent to `Item::upsert()`.
12. All existing import tests still pass.

---

## Affected Files

| File | Change |
|---|---|
| `resources/views/components/⚡item-manager.blade.php` | Add `$selectedIds`, `$selectAll`, `bulkDelete()`, `toggleSelectAll()`, `updatedSelectAll()`, checkbox column, toolbar button |
| `resources/views/components/⚡import-items.blade.php` | Add `$selectedRows` (keyed by preview index), `selectAllRows()`, toggle; filter `confirm()` by selection |
| `tests/Feature/Inventory/BulkDeleteTest.php` | NEW — feature tests for bulk delete |
| `tests/Feature/Inventory/ImportItemsTest.php` | EXTEND — add selective import test cases |

No new files in `app/` — all logic lives in the SFC components.

---

## Task Breakdown

> Each task ≤ 10 minutes. `INPUT → OUTPUT → VERIFY`.

### T1 — Item Manager: add `selectedIds` state + checkbox column

- **Agent / Skill:** `frontend-specialist` / `livewire-development`
- **Priority:** P0
- **Dependencies:** none
- **INPUT:** existing `⚡item-manager.blade.php`
- **OUTPUT:**
  - New Livewire property `public array $selectedIds = []` (stores item IDs as strings, matching Livewire's wire:model checkbox behavior).
  - New Livewire property `public bool $selectAll = false`.
  - `updatedSelectAll(bool $value): void` — when header checkbox toggled, fills `selectedIds` with all IDs on current page or clears.
  - `updatedSelectedIds(): void` — when individual checkboxes change, recalculates `$selectAll` state (checked if all current-page IDs are selected).
  - In the table: new `<th>` with a checkbox for select-all at position 0 (before existing columns). Each `<tr>` gets a matching `<td>` with `wire:model="selectedIds"` checkbox with value `{{ $item->id }}`.
  - `resetPage()` also calls `$this->reset('selectedIds', 'selectAll')` via `updatingSearch()` / `updatingFilterType()` / `sortBy()` to clear selection on page/filter/sort change.
- **VERIFY:** Check/uncheck a row updates `selectedIds`; header checkbox selects all on page; navigating to next page clears selection.

### T2 — Item Manager: `bulkDelete()` action + toolbar button

- **Agent / Skill:** `frontend-specialist` / `livewire-development`
- **Priority:** P0
- **Dependencies:** T1
- **INPUT:** `selectedIds` property from T1
- **OUTPUT:**
  - `bulkDelete(): void` — validates `selectedIds` is non-empty, runs `Item::whereIn('id', $this->selectedIds)->delete()`, flashes `'Deleted N items.'`, resets selection, calls `resetPage()`.
  - In the toolbar (flex row with search/filter): a "Delete selected ({{ count($selectedIds) }})" button with `wire:click="bulkDelete"` and `wire:confirm="Delete N items?"` that is `@if (count($selectedIds) > 0)` visible, styled red.
- **VERIFY:** Select 3 rows, click Delete selected, confirm → 3 rows gone, count resets to 0, flash message shows.

### T3 — Item Manager: tests for bulk delete

- **Agent / Skill:** `test-engineer` / `laravel-best-practices`
- **Priority:** P1
- **Dependencies:** T2
- **INPUT:** `ItemFactory`, `Livewire::test('item-manager')`
- **OUTPUT:** `tests/Feature/Inventory/BulkDeleteTest.php`
  1. `test_bulk_delete_removes_selected_items()` — create 5 items via factory, select 3, call `bulkDelete()`, assert 2 remain.
  2. `test_bulk_delete_with_no_selection_does_nothing()` — call `bulkDelete()` with empty `selectedIds`, assert item count unchanged.
  3. `test_select_all_selects_current_page()` — 15 items, first page shows 10, toggle `selectAll=true`, assert `selectedIds` has 10 entries.
  4. `test_selection_clears_on_search()` — select items, change `search`, assert `selectedIds` is empty.
- **VERIFY:** `php artisan test --compact --filter=BulkDeleteTest` all green.

### T4 — Import Preview: add `selectedRows` state + per-row checkboxes

- **Agent / Skill:** `frontend-specialist` / `livewire-development`
- **Priority:** P0
- **Dependencies:** none (parallel to T1–T2)
- **INPUT:** existing `⚡import-items.blade.php`, `$preview` array (keyed 0…N)
- **OUTPUT:**
  - New property `public array $selectedRows = []` — array of preview-index strings that are checked. Populated with all "ready" (non-null attributes) indices when `updatedFile()` fills `$preview`.
  - `selectAllRows(): void` — sets `selectedRows` to all ready indices.
  - `deselectAllRows(): void` — clears `selectedRows`.
  - `confirm()` filters `$this->preview` by `selectedRows` before building `$valid`.
  - `with()` re-derives `validCount` from `count($this->selectedRows)` (only checked ready rows).
  - In the preview table: new `<th>` for checkbox; "ready" rows get `wire:model="selectedRows"` checkbox (value = string index); "skipped" rows get an empty `<td>`. A "Select all / Deselect all" link above the table.
  - Checked rows display normally; unchecked ready rows get `opacity-50` or `line-through text-gray-400` styling.
- **VERIFY:** Upload fixture → all ready rows pre-checked; uncheck 5 → count in button drops by 5; re-check → count rises; confirm imports only checked rows.

### T5 — Import: extend tests for selective import

- **Agent / Skill:** `test-engineer` / `laravel-best-practices`
- **Priority:** P1
- **Dependencies:** T4
- **INPUT:** `tests/Feature/Inventory/ImportItemsTest.php`, existing fixture
- **OUTPUT:** Two new test methods added to `ImportItemsTest`:
  1. `test_deselecting_rows_excludes_them_from_import()` — upload fixture, deselect all rows, call `confirm()`, assert `Item::count() === 0`.
  2. `test_partial_selection_imports_only_checked_rows()` — upload fixture, reduce `selectedRows` to 2 indices, call `confirm()`, assert `Item::count() === 2`.
- **VERIFY:** `php artisan test --compact --filter=ImportItemsTest` all green (existing + new).

### T6 — Pint

- **Agent / Skill:** `backend-specialist`
- **Priority:** P2
- **Dependencies:** T1–T5
- **OUTPUT:** `vendor/bin/pint --dirty --format agent` clean on all changed files.
- **VERIFY:** exit 0.

---

## Dependency Graph

```
T1 ──► T2 ──► T3 ──► T6
T4 ──► T5 ───────────┘
```

T1–T3 (items table) and T4–T5 (import preview) are fully independent and can be developed in parallel.

---

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| `selectedIds` persists across pages if `resetPage()` doesn't clear it | M | M | Call `$this->reset('selectedIds', 'selectAll')` inside `updatingSearch`, `updatingFilterType`, `sortBy`, `resetForm` |
| `wire:model` checkbox on `selectedIds` requires string values (Livewire casts) | M | L | Cast IDs to string in `updatedSelectAll`: `array_map('strval', $ids)` |
| `selectedRows` indices shift if `$preview` is rebuilt on re-upload | L | M | `updatedFile()` always resets `selectedRows` before re-populating |
| Bulk deleting items that are on another page (cross-page selection) | L | M | Out of scope: selection is page-scoped; documented in Phase X |
| Pint reformats SFC blade PHP block and changes readable layout | L | L | Run Pint last per CLAUDE.md rules |

---

## Out of Scope

- Cross-page bulk selection (selecting all items across all pages).
- Bulk edit (changing fields on multiple items at once).
- Export of selected rows.
- Undo / soft-delete on bulk delete.

---

## Phase X: Verification Checklist

Run after implementation — do not pre-tick.

**Bulk Delete**
- [ ] `php artisan test --compact --filter=BulkDeleteTest` — all green
- [ ] Manual: check 3 rows, "Delete selected (3)" button appears; confirm → rows gone
- [ ] Manual: header checkbox selects all visible rows on page; deselects on second click
- [ ] Manual: search/sort/page navigation clears selection
- [ ] Manual: individual Edit/Delete buttons still work

**Selective Import**
- [ ] `php artisan test --compact --filter=ImportItemsTest` — all green (including new tests)
- [ ] Manual: upload fixture → all ready rows pre-checked; "Confirm import (N)" matches ready count
- [ ] Manual: uncheck 5 rows → count decreases; re-check → count restores
- [ ] Manual: "Select all / Deselect all" toggle works
- [ ] Manual: skipped rows have no checkbox
- [ ] Manual: unchecked rows show dimmed style but remain in preview
- [ ] Manual: confirm with mixed selection → only checked rows in DB

**Regression**
- [ ] `php artisan test --compact` full suite (excluding pre-existing ExampleTest failure)
- [ ] `vendor/bin/pint --test --format agent` on modified files — exit 0
- [ ] No browser-console errors (`browser-logs` MCP clean)

### Phase X Completion Marker (fill on completion)

```
## ✅ PHASE X COMPLETE
- Bulk Delete tests: ✅
- Selective Import tests: ✅
- Pint: ✅
- Manual verified: ✅
- Date: 2026-__-__
```
