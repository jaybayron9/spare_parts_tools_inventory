<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $samples = [
            ['name' => 'Hex Bolt M8 x 30mm',  'sku' => 'SP-HB-M8-30',  'type' => Item::TYPE_SPARE_PART, 'category' => 'Fasteners', 'quantity' => 240, 'reorder_level' => 50, 'unit_price' => 0.35,  'location' => 'A-12'],
            ['name' => 'Ball Bearing 6204',   'sku' => 'SP-BB-6204',   'type' => Item::TYPE_SPARE_PART, 'category' => 'Bearings',  'quantity' => 18,  'reorder_level' => 25, 'unit_price' => 4.80,  'location' => 'A-04'],
            ['name' => 'V-Belt A-44',         'sku' => 'SP-VB-A44',    'type' => Item::TYPE_SPARE_PART, 'category' => 'Belts',     'quantity' => 12,  'reorder_level' => 10, 'unit_price' => 7.25,  'location' => 'B-02'],
            ['name' => 'Air Filter HEPA H13', 'sku' => 'SP-AF-H13',    'type' => Item::TYPE_SPARE_PART, 'category' => 'Filters',   'quantity' => 4,   'reorder_level' => 8,  'unit_price' => 22.00, 'location' => 'B-09'],
            ['name' => 'Hydraulic Hose 1/2"', 'sku' => 'SP-HH-12',     'type' => Item::TYPE_SPARE_PART, 'category' => 'Hoses',     'quantity' => 30,  'reorder_level' => 15, 'unit_price' => 12.50, 'location' => 'C-01'],

            ['name' => 'Cordless Drill 18V',  'sku' => 'TL-CD-18V',    'type' => Item::TYPE_TOOL, 'category' => 'Power Tools', 'quantity' => 5,  'reorder_level' => 2, 'unit_price' => 119.00, 'location' => 'Cabinet 1'],
            ['name' => 'Torque Wrench 1/2"',  'sku' => 'TL-TW-12',     'type' => Item::TYPE_TOOL, 'category' => 'Hand Tools',  'quantity' => 3,  'reorder_level' => 2, 'unit_price' => 85.00,  'location' => 'Cabinet 2'],
            ['name' => 'Digital Multimeter',  'sku' => 'TL-DMM-01',    'type' => Item::TYPE_TOOL, 'category' => 'Measuring',   'quantity' => 4,  'reorder_level' => 3, 'unit_price' => 45.00,  'location' => 'Cabinet 3'],
            ['name' => 'Angle Grinder 4.5"',  'sku' => 'TL-AG-45',     'type' => Item::TYPE_TOOL, 'category' => 'Power Tools', 'quantity' => 2,  'reorder_level' => 2, 'unit_price' => 75.00,  'location' => 'Cabinet 1'],
            ['name' => 'Socket Set 1/4"',     'sku' => 'TL-SS-14',     'type' => Item::TYPE_TOOL, 'category' => 'Hand Tools',  'quantity' => 6,  'reorder_level' => 3, 'unit_price' => 39.00,  'location' => 'Cabinet 2'],
        ];

        foreach ($samples as $sample) {
            Item::updateOrCreate(['sku' => $sample['sku']], $sample);
        }
    }
}
