<?php

namespace App\Imports;

use OpenSpout\Reader\XLSX\Reader;

class ItemsXlsxParser
{
    /**
     * Map of recognized xlsx header text → canonical key used by ItemRowMapper.
     * Covers both the original procurement format and the app's own export format.
     *
     * @var array<string, string>
     */
    public const HEADERS = [
        // Original procurement format
        'SPARE PARTS DESCRIPTION' => 'description',
        'Vendor' => 'vendor',
        'Equipment/System' => 'equipment_system',
        'Contract' => 'contract',
        'Brand' => 'brand',
        'Part Number' => 'part_number',
        'Installation and Programming remarks' => 'install_remarks',
        'Critical Spare (Yes or No)' => 'is_critical',
        'UOM' => 'uom',
        'PARTS QUANTITY FOR REGULAR STOCKING' => 'quantity',
        'Leadtime' => 'leadtime',
        'MIN. QTY. TO TRIGER REPLENISHMENT' => 'reorder_level',
        'STORAGE FACILITY' => 'storage_facility',
        'DATE PURCHASED' => 'date_purchased',
        'PARTS SERVICE LIFE (YRS)' => 'service_life_yrs',
        'PARTS EUL (YR)' => 'eul_yrs',
        'FREQUENCY OF REPLACEMENT' => 'replacement_frequency',
        'REMARKS' => 'remarks',

        // App export format
        'SKU' => 'part_number',
        'Name' => 'description',
        'Type' => 'type_label',
        'Category' => 'category',
        'Quantity' => 'quantity',
        'Reorder Level' => 'reorder_level',
        'Unit Price' => 'unit_price',
        'Location' => 'storage_facility',
        'Equipment System' => 'equipment_system',
        'Is Critical' => 'is_critical',
        'Date Purchased' => 'date_purchased',
        'Service Life (yrs)' => 'service_life_yrs',
        'EUL (yrs)' => 'eul_yrs',
        'Replacement Frequency' => 'replacement_frequency',
        'Notes' => 'remarks',
    ];

    /** Canonical keys that must be present in any valid file. */
    private const REQUIRED_KEYS = ['part_number', 'description'];

    /**
     * Read sheet 1 of an xlsx and return associative rows keyed by canonical names.
     *
     * Auto-detects whether row 1 is a banner (original format) or the header row
     * (export format) by checking if any cell matches a known header label.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidXlsxException
     */
    public function parse(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new InvalidXlsxException("File not found: {$absolutePath}");
        }

        $reader = new Reader;

        try {
            $reader->open($absolutePath);
        } catch (\Throwable $e) {
            throw new InvalidXlsxException('Could not open xlsx: '.$e->getMessage(), previous: $e);
        }

        $rows = [];
        $headerKeys = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowIndex = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;
                    $cells = array_map(
                        fn ($v) => is_string($v) ? trim($v) : $v,
                        $row->toArray(),
                    );

                    if ($rowIndex === 1) {
                        if ($this->looksLikeHeader($cells)) {
                            // Export format: row 1 is the header, no banner.
                            $headerKeys = $this->resolveHeaderKeys($cells);
                        }

                        // Original format: row 1 is the banner — skip it.
                        continue;
                    }

                    if ($headerKeys === null) {
                        // Original format: row 2 is the header.
                        $headerKeys = $this->resolveHeaderKeys($cells);

                        continue;
                    }

                    if ($this->isBlank($cells)) {
                        continue;
                    }

                    $rows[] = $this->buildAssoc($headerKeys, $cells);
                }
                break; // sheet 1 only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * Returns true when at least one cell matches a recognized header label,
     * meaning row 1 is a header row rather than a banner.
     *
     * @param  array<int, mixed>  $cells
     */
    private function looksLikeHeader(array $cells): bool
    {
        foreach ($cells as $cell) {
            $label = is_string($cell) ? $cell : '';
            if ($label !== '' && isset(self::HEADERS[$label])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, ?string>
     *
     * @throws InvalidXlsxException
     */
    private function resolveHeaderKeys(array $cells): array
    {
        $keys = [];
        $found = [];
        foreach ($cells as $cell) {
            $label = is_string($cell) ? trim($cell) : '';
            $key = self::HEADERS[$label] ?? null;
            $keys[] = $key;
            if ($key !== null) {
                $found[$key] = true;
            }
        }

        $missing = array_diff(self::REQUIRED_KEYS, array_keys($found));
        if (! empty($missing)) {
            throw new InvalidXlsxException(
                'Header row is missing required columns: '.implode(', ', $missing),
            );
        }

        return $keys;
    }

    /**
     * @param  array<int, ?string>  $headerKeys
     * @param  array<int, mixed>  $cells
     * @return array<string, mixed>
     */
    private function buildAssoc(array $headerKeys, array $cells): array
    {
        $assoc = [];
        foreach ($headerKeys as $i => $key) {
            if ($key === null) {
                continue;
            }
            $assoc[$key] = $cells[$i] ?? null;
        }

        return $assoc;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $c) {
            if ($c !== null && $c !== '') {
                return false;
            }
        }

        return true;
    }
}
