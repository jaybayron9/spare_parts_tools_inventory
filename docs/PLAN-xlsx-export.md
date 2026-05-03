# PLAN: Export to XLSX (Selective)

## Goal
Add an "Export" button to the inventory page that downloads a `.xlsx` file. When items are selected via the existing checkboxes, only those items are exported. When nothing is selected, all items matching the current search/filter are exported.

---

## Decisions

| Question | Decision |
|----------|----------|
| Library | `openspout/openspout` v5 (already installed — used for import) |
| Selection | Reuse existing `$selectedIds` checkboxes — no new UI needed |
| No selection | Export all items matching current `$search` + `$filterType` |
| Delivery | Streamed HTTP download via a dedicated controller (`ExportItemsController`) triggered by a plain `<a href>` — avoids Livewire binary response limitations |
| Export scope passed | Livewire generates a signed URL with selected IDs (or "all" flag + current filters) that the controller resolves |
| Column set | All meaningful columns: SKU, Name, Type, Category, Quantity, Reorder Level, Unit Price, Location, Vendor, Brand, Equipment System, Contract, Is Critical, UOM, Leadtime, Date Purchased, Service Life (yrs), EUL (yrs), Replacement Frequency, Notes |
| Filename | `inventory-export-YYYY-MM-DD.xlsx` |

---

## How the URL signing works

Livewire calls a `exportUrl()` computed property (or a method) that builds a signed route URL encoding:
- `ids` — comma-joined selected IDs, OR absent if exporting all
- `search` — current search string (only when exporting all)
- `filter_type` — current filter type ID (only when exporting all)

The controller validates the signature, resolves the query, and streams the XLSX.

---

## Task Breakdown

### T1 — `ExportItemsController`
**File:** `app/Http/Controllers/ExportItemsController.php`

- Invokable, validates signed URL via `request()->hasValidSignature()`
- Resolves items:
  - If `ids` param present → `Item::with('itemType')->whereIn('id', explode(',', $ids))->orderBy('name')->get()`
  - Else → `Item::with('itemType')->when(search)->when(filter_type)->orderBy('name')->get()`
- Streams XLSX using openspout `Writer`:
  ```php
  $writer = new \OpenSpout\Writer\XLSX\Writer();
  $writer->openToFile($tmpFile);
  $writer->addRow(Row::fromValues([...headers...]));
  foreach ($items as $item) { $writer->addRow(Row::fromValues([...])); }
  $writer->close();
  return response()->download($tmpFile, 'inventory-export-'.now()->toDateString().'.xlsx')
      ->deleteFileAfterSend();
  ```
- Handles empty result (still produces valid XLSX with just headers)

### T2 — Route
**File:** `routes/web.php`

```php
Route::get('/export-items', ExportItemsController::class)
    ->name('items.export')
    ->middleware('signed');
```

### T3 — Update `⚡item-manager` SFC
**File:** `resources/views/components/⚡item-manager.blade.php`

**PHP changes:**
- Add `#[Computed]` `exportUrl()` method:
  ```php
  #[Computed]
  public function exportUrl(): string
  {
      $params = empty($this->selectedIds)
          ? ['search' => $this->search, 'filter_type' => $this->filterType]
          : ['ids' => implode(',', $this->selectedIds)];
      return URL::signedRoute('items.export', $params);
  }
  ```

**Blade changes:**
- Add "Export" button in the toolbar (beside "+ New Item"):
  - When items selected: label = "Export selected (N)"
  - When nothing selected: label = "Export all" (respects current search/filter)
  - Rendered as `<a href="{{ $this->exportUrl }}" ...>` so it triggers a file download without a Livewire round-trip

### T4 — Feature Tests
**File:** `tests/Feature/Inventory/ExportItemsTest.php`

Tests:
1. `test_export_all_returns_xlsx_download()` — GET signed URL, assert 200 + correct content-type + filename header
2. `test_export_selected_ids_only()` — sign URL with ids param, assert only those items in response (check Content-Disposition)
3. `test_export_respects_search_filter()` — sign with search param, verify scoped
4. `test_unsigned_url_is_rejected()` — GET without signature, assert 403
5. `test_export_with_no_items_still_downloads()` — empty DB, assert valid download (header row only)

### T5 — Pint
`vendor/bin/pint --dirty --format agent`

---

## Execution Order

```
T1 (controller) → T2 (route) → T3 (SFC update) → T4 (tests) → T5 (pint)
```

---

## Files Touched

| File | Change |
|------|--------|
| `app/Http/Controllers/ExportItemsController.php` | New invokable controller |
| `routes/web.php` | Add signed export route |
| `resources/views/components/⚡item-manager.blade.php` | `exportUrl()` computed + Export button |
| `tests/Feature/Inventory/ExportItemsTest.php` | New feature tests |

---

## Verification Checklist

- [ ] Clicking "Export all" downloads a valid `.xlsx` with all visible items
- [ ] Selecting rows then clicking "Export selected (N)" downloads only those rows
- [ ] Current search/filter is respected when exporting all
- [ ] Unsigned URL returns 403
- [ ] File opens correctly in Excel/LibreOffice
- [ ] `php artisan test --compact` — full suite green
