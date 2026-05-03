# PLAN: Custom Item Types

## Goal
Replace the hard-coded `Item::TYPES` constant with a DB-driven `item_types` table. Users can add, edit, and delete custom types from an inline management panel on the inventory page. "All" is a UI-only concept (empty filter) — not stored in the database. "Spare Part" and "Tool" are seeded as regular records and can be edited or deleted. Deleting a type reassigns its items to the first remaining type.

---

## Decisions

| Question | Decision |
|----------|----------|
| Data model | `item_types` table with `id`, `label`; `items` gets `item_type_id` FK replacing `type` string |
| "All" filter | Hard-coded empty option in the dropdown — no DB record |
| Default types | "Spare Part" and "Tool" seeded as normal records (editable/deletable) |
| On delete | Reassign items to first remaining type (ordered by id); block if only 1 type left |
| UI location | Collapsible inline panel on the inventory page, toggled by "Manage" link beside the Type filter |
| Slug/label | Only `label` stored (e.g. "Spare Part"). No slug — FK is used for filtering |

---

## Task Breakdown

### T1 — Migration: `item_types` + migrate `items.type` → FK
**File:** `database/migrations/YYYY_create_item_types_and_migrate_items.php`

- Create `item_types`: `id`, `label` (string, unique), timestamps
- Insert seed rows inline in the migration: 'Spare Part', 'Tool'
- Add `item_type_id` (nullable unsigned bigint) to `items`
- `UPDATE items SET item_type_id = (SELECT id FROM item_types WHERE label = CASE type WHEN 'spare_part' THEN 'Spare Part' WHEN 'tool' THEN 'Tool' END)`
- Make `item_type_id` NOT NULL (set default to Spare Part id for any null stragglers)
- Add FK constraint (`nullOnDelete` → set null, then coerce in app logic) — actually use `restrictOnDelete` since we reassign in app before deleting
- Drop `type` column + its index
- Add index on `items.item_type_id`

Down: recreate `type` column, back-fill from join, drop `item_type_id`.

---

### T2 — `ItemType` Model
**File:** `app/Models/ItemType.php` (via `php artisan make:model ItemType`)

```php
$fillable = ['label'];
// relationships:
items(): HasMany → Item::class
```

---

### T3 — Update `Item` Model
**File:** `app/Models/Item.php`

- Remove `TYPE_SPARE_PART`, `TYPE_TOOL`, `TYPES` constants
- Remove `type` from `$fillable`
- Add `item_type_id` to `$fillable`
- Add `itemType(): BelongsTo → ItemType::class`
- Update `typeName()` accessor (if it exists) to `$this->itemType?->label ?? '—'`
- Update factory reference (see T4)

---

### T4 — Update `ItemFactory`
**File:** `database/factories/ItemFactory.php`

- Replace `'type' => fake()->randomElement(array_keys(Item::TYPES))` with `'item_type_id' => ItemType::inRandomOrder()->first()?->id ?? ItemType::factory()->create()->id`

---

### T5 — `ItemTypeManager` Livewire SFC
**File:** `resources/views/components/⚡item-type-manager.blade.php`

**PHP class properties:**
```php
public bool $showForm = false;
public ?int $editingId = null;
public string $typeName = '';
```

**Methods:**
- `create()` — reset form, show form
- `edit(int $id)` — load label into `$typeName`, set `$editingId`, show form
- `save()` — validate `typeName` required|unique:item_types,label[,editingId]`; create or update; flash message; hide form
- `delete(int $id)` — guard: if `ItemType::count() <= 1` flash error and return; find first other type; `Item::where('item_type_id', $id)->update(['item_type_id' => $fallback->id])`; delete; flash message; dispatch `typesUpdated`
- `cancel()` — reset form, hide form

**Computed:**
- `types()` — `ItemType::withCount('items')->orderBy('label')->get()`

**Events dispatched:** `$dispatch('typesUpdated')` after create/edit/delete so `item-manager` can refresh its filter options.

**UI rules:**
- Table: Label | Items count | Actions (Edit / Delete)
- Can't delete when only 1 type remains — show disabled Delete with tooltip "At least one type required"
- Flash messages for created / updated / deleted
- Form: single "Type name" text input + Save/Cancel buttons

---

### T6 — Update `⚡item-manager` SFC
**File:** `resources/views/components/⚡item-manager.blade.php`

**PHP changes:**
- Replace `Item::TYPES` constant references with `ItemType::orderBy('label')->get()` in the filter dropdown computed or inline
- `$filterType` changes from a string slug to a string-cast integer (item_type_id)
- Filter query: `->when($this->filterType, fn($q) => $q->where('item_type_id', $this->filterType))`
- Add `$showTypeManager = false` property + `toggleTypeManager()` method
- Listen for `typesUpdated` with `#[On('typesUpdated')]` to reset `$filterType` if the selected type was deleted

**Blade changes:**
- Filter dropdown: prepend hard-coded `<option value="">All</option>`, then loop `ItemType::orderBy('label')->get()`
- Add "Manage" text link beside the "Type" filter label that calls `toggleTypeManager()`
- Below the toolbar: `@if ($showTypeManager) <livewire:⚡item-type-manager /> @endif`

---

### T7 — Feature Tests

**File:** `tests/Feature/Inventory/ItemTypeManagerTest.php`

Tests:
1. `test_can_create_a_new_type()` — fill form, save, assert DB has label, assert in filter dropdown
2. `test_can_edit_an_existing_type()` — edit "Spare Part" label to "Electrical Part", assert updated
3. `test_can_delete_a_type_and_items_are_reassigned()` — create 2 types, assign items to type A, delete type A, assert items now have type B
4. `test_cannot_delete_the_last_remaining_type()` — delete all but one, attempt delete, assert count still 1 + flash error
5. `test_filter_by_type_uses_item_type_id()` — create 2 types + items, filter by one type id, assert correct items returned
6. `test_deleting_active_filter_type_resets_filter()` — set filterType to type id, delete that type, assert filterType reset to ''

**File:** `tests/Unit/Models/ItemTypeTest.php`

Tests:
1. `test_items_relationship_returns_items()` — basic relationship check

---

### T8 — Pint
Run `vendor/bin/pint --dirty --format agent` on all changed PHP files.

---

## Execution Order

```
T1 (migration) → T2 (ItemType model) → T3 (Item model) → T4 (factory)
→ T5 (ItemTypeManager SFC) → T6 (item-manager updates) → T7 (tests) → T8 (pint)
```

T1–T4 must be sequential (each builds on the previous schema/model). T5 and T6 can overlap. T7 after T5+T6.

---

## Files Touched

| File | Change |
|------|--------|
| `database/migrations/YYYY_...php` | New migration (create table + migrate data) |
| `app/Models/ItemType.php` | New model |
| `app/Models/Item.php` | Remove constants, swap `type` for `item_type_id` |
| `database/factories/ItemFactory.php` | Use `item_type_id` |
| `resources/views/components/⚡item-type-manager.blade.php` | New Livewire SFC |
| `resources/views/components/⚡item-manager.blade.php` | Filter + toggle + listener |
| `tests/Feature/Inventory/ItemTypeManagerTest.php` | New feature tests |
| `tests/Unit/Models/ItemTypeTest.php` | New unit test |

---

## Verification Checklist

- [ ] Migration runs cleanly up and down without data loss
- [ ] Existing items retain their correct type after migration
- [ ] "All" shows all items (empty filter value)
- [ ] Creating a custom type appears immediately in filter dropdown
- [ ] Editing a type label updates the dropdown option
- [ ] Deleting a type reassigns items; deleted type no longer in dropdown
- [ ] Deleting the last type is blocked with a user-facing message
- [ ] `php artisan test --compact` — full suite green
