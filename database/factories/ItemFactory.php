<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'vendor' => $this->faker->company(),
            'brand' => $this->faker->company(),
            'equipment_system' => $this->faker->randomElement(['HVAC', 'Chiller', 'Pump', 'Boiler']),
            'contract' => $this->faker->randomElement(['OFCI', 'CFCI', null]),
            'is_critical' => $this->faker->boolean(),
            'uom' => $this->faker->randomElement(['Pcs', 'Set', 'Box']),
            'install_remarks' => null,
            'sku' => strtoupper($this->faker->bothify('???######')),
            'type' => Item::TYPE_SPARE_PART,
            'category' => $this->faker->word(),
            'quantity' => $this->faker->numberBetween(0, 50),
            'reorder_level' => $this->faker->numberBetween(0, 10),
            'leadtime' => $this->faker->randomElement(['1 week', '12 weeks', null]),
            'unit_price' => $this->faker->randomFloat(2, 1, 500),
            'location' => $this->faker->randomElement(['STT FV', 'Warehouse A', 'Warehouse B']),
            'date_purchased' => null,
            'service_life_yrs' => $this->faker->optional()->numberBetween(1, 20),
            'eul_yrs' => $this->faker->optional()->numberBetween(1, 30),
            'replacement_frequency' => $this->faker->randomElement(['As needed', 'Annually', null]),
            'notes' => null,
        ];
    }
}
