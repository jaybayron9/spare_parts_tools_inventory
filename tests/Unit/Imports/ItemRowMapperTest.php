<?php

namespace Tests\Unit\Imports;

use App\Imports\ItemRowMapper;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemRowMapperTest extends TestCase
{
    use RefreshDatabase;

    private ItemRowMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ItemRowMapper;
    }

    public function test_maps_a_complete_row(): void
    {
        $sparePartId = ItemType::where('label', 'Spare Part')->value('id');

        $result = $this->mapper->map([
            'description' => 'Temperature Sensor, Overmolded',
            'vendor' => 'Trane',
            'equipment_system' => 'Chiller',
            'contract' => 'OFCI',
            'brand' => 'Trane',
            'part_number' => 'SEN02133',
            'install_remarks' => 'programming required',
            'is_critical' => 'Yes',
            'uom' => 'Pcs',
            'quantity' => 5,
            'leadtime' => '12 weeks',
            'reorder_level' => 2,
            'storage_facility' => 'STT FV',
            'date_purchased' => '2024-03-01',
            'service_life_yrs' => '10',
            'eul_yrs' => '15',
            'replacement_frequency' => 'As needed',
            'remarks' => 'Critical for cooling',
        ]);

        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['attributes']);
        $a = $result['attributes'];
        $this->assertSame('SEN02133', $a['sku']);
        $this->assertSame('Temperature Sensor, Overmolded', $a['name']);
        $this->assertTrue($a['is_critical']);
        $this->assertSame(5, $a['quantity']);
        $this->assertSame(2, $a['reorder_level']);
        $this->assertSame('Chiller', $a['category']);
        $this->assertSame('STT FV', $a['location']);
        $this->assertSame('2024-03-01', $a['date_purchased']);
        $this->assertSame(10, $a['service_life_yrs']);
        $this->assertSame($sparePartId, $a['item_type_id']);
    }

    public function test_missing_part_number_returns_error_and_null_attributes(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => '',
        ]);

        $this->assertNull($result['attributes']);
        $this->assertContains('Missing Part Number', $result['errors']);
    }

    public function test_missing_description_returns_error(): void
    {
        $result = $this->mapper->map([
            'description' => '',
            'part_number' => 'SKU1',
        ]);

        $this->assertNull($result['attributes']);
        $this->assertContains('Missing Spare Parts Description', $result['errors']);
    }

    public function test_non_numeric_quantity_falls_back_to_zero_and_warns(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => 'SKU2',
            'quantity' => 'On stock c/o Trane',
        ]);

        $this->assertSame(0, $result['attributes']['quantity']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('On stock c/o Trane', $result['attributes']['notes']);
    }

    public function test_tbc_date_becomes_null(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => 'SKU3',
            'date_purchased' => 'TBC',
        ]);

        $this->assertNull($result['attributes']['date_purchased']);
    }

    public function test_critical_no_is_false(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => 'SKU4',
            'is_critical' => 'No',
        ]);

        $this->assertFalse($result['attributes']['is_critical']);
    }

    public function test_whitespace_is_collapsed_and_trimmed(): void
    {
        $result = $this->mapper->map([
            'description' => "  Foo   Bar  \n",
            'part_number' => '  SKU5 ',
        ]);

        $this->assertSame('Foo Bar', $result['attributes']['name']);
        $this->assertSame('SKU5', $result['attributes']['sku']);
    }

    public function test_category_falls_back_to_equipment_system(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => 'SKU6',
            'equipment_system' => 'HVAC',
        ]);

        $this->assertSame('HVAC', $result['attributes']['category']);
        $this->assertSame('HVAC', $result['attributes']['equipment_system']);
    }

    public function test_explicit_type_label_resolves_to_correct_item_type_id(): void
    {
        $toolId = ItemType::where('label', 'Tool')->value('id');

        $result = $this->mapper->map([
            'description' => 'Wrench',
            'part_number' => 'WRN001',
            'type_label' => 'Tool',
        ]);

        $this->assertSame($toolId, $result['attributes']['item_type_id']);
    }

    public function test_unit_price_is_mapped_from_export_format(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => 'SKU7',
            'unit_price' => '149.99',
        ]);

        $this->assertSame(149.99, $result['attributes']['unit_price']);
    }

    public function test_explicit_category_takes_precedence_over_equipment_system(): void
    {
        $result = $this->mapper->map([
            'description' => 'Foo',
            'part_number' => 'SKU8',
            'category' => 'Sensors',
            'equipment_system' => 'Chiller',
        ]);

        $this->assertSame('Sensors', $result['attributes']['category']);
    }
}
