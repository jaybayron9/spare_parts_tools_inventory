<?php

namespace App\Imports;

use App\Models\ItemType;
use Carbon\Carbon;
use DateTimeInterface;

class ItemRowMapper
{
    /**
     * Convert a parsed xlsx row into Item-fillable attributes plus row diagnostics.
     *
     * Returned shape:
     *  [
     *    'attributes' => array<string, mixed>|null,  // null when row is unmappable
     *    'errors' => list<string>,
     *    'warnings' => list<string>,
     *  ]
     *
     * @param  array<string, mixed>  $row
     * @return array{attributes: ?array<string, mixed>, errors: list<string>, warnings: list<string>}
     */
    public function map(array $row): array
    {
        $errors = [];
        $warnings = [];

        $sku = $this->str($row['part_number'] ?? null);
        $name = $this->str($row['description'] ?? null);

        if ($sku === null) {
            $errors[] = 'Missing Part Number';
        }
        if ($name === null) {
            $errors[] = 'Missing Spare Parts Description';
        }

        if (! empty($errors)) {
            return ['attributes' => null, 'errors' => $errors, 'warnings' => $warnings];
        }

        $rawQuantity = $row['quantity'] ?? null;
        [$quantity, $quantityNote] = $this->parseQuantity($rawQuantity);
        if ($quantityNote !== null) {
            $warnings[] = "Quantity not numeric (kept as note): \"{$quantityNote}\"";
        }

        $reorder = $this->parseInt($row['reorder_level'] ?? null) ?? 0;

        $remarks = $this->str($row['remarks'] ?? null);
        $notes = $remarks;
        if ($quantityNote !== null) {
            $prefix = "[import] quantity: {$quantityNote}";
            $notes = $notes === null ? $prefix : ($prefix."\n".$notes);
        }

        $equipmentSystem = $this->str($row['equipment_system'] ?? null);

        // Resolve item_type_id: prefer explicit type label (export format), fall back to Spare Part.
        $typeLabel = $this->str($row['type_label'] ?? null);
        $itemTypeId = $typeLabel
            ? (ItemType::where('label', $typeLabel)->value('id') ?? ItemType::orderBy('id')->value('id'))
            : (ItemType::where('label', 'Spare Part')->value('id') ?? ItemType::orderBy('id')->value('id'));

        $attrs = [
            'name' => $name,
            'vendor' => $this->str($row['vendor'] ?? null),
            'brand' => $this->str($row['brand'] ?? null),
            'equipment_system' => $equipmentSystem,
            'contract' => $this->str($row['contract'] ?? null),
            'is_critical' => $this->parseBool($row['is_critical'] ?? null),
            'uom' => $this->str($row['uom'] ?? null),
            'install_remarks' => $this->str($row['install_remarks'] ?? null),
            'sku' => $sku,
            'item_type_id' => $itemTypeId,
            'category' => $this->str($row['category'] ?? null) ?? $equipmentSystem,
            'quantity' => $quantity,
            'reorder_level' => $reorder,
            'unit_price' => $this->parseFloat($row['unit_price'] ?? null) ?? 0,
            'leadtime' => $this->str($row['leadtime'] ?? null),
            'location' => $this->str($row['storage_facility'] ?? null),
            'date_purchased' => $this->parseDate($row['date_purchased'] ?? null),
            'service_life_yrs' => $this->parseInt($row['service_life_yrs'] ?? null),
            'eul_yrs' => $this->parseInt($row['eul_yrs'] ?? null),
            'replacement_frequency' => $this->str($row['replacement_frequency'] ?? null),
            'notes' => $notes,
        ];

        return ['attributes' => $attrs, 'errors' => $errors, 'warnings' => $warnings];
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (! is_string($v)) {
            $v = (string) $v;
        }
        $v = preg_replace('/\s+/', ' ', trim($v)) ?? '';

        return $v === '' ? null : $v;
    }

    /**
     * @return array{0: int, 1: ?string} [quantity, originalStringIfNonNumeric]
     */
    private function parseQuantity(mixed $v): array
    {
        if ($v === null || $v === '') {
            return [0, null];
        }
        if (is_int($v)) {
            return [$v, null];
        }
        if (is_float($v)) {
            return [(int) $v, null];
        }
        $s = trim((string) $v);
        if (is_numeric($s)) {
            return [(int) $s, null];
        }

        return [0, $s];
    }

    private function parseInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        $s = trim((string) $v);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (int) $s;
    }

    private function parseFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }
        $s = trim((string) $v);

        return is_numeric($s) ? (float) $s : null;
    }

    private function parseBool(mixed $v): bool
    {
        $s = strtolower(trim((string) ($v ?? '')));

        return in_array($s, ['yes', 'y', 'true', '1'], true);
    }

    private function parseDate(mixed $v): ?string
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '' || strtoupper($s) === 'TBC' || strtoupper($s) === 'N/A') {
            return null;
        }
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
