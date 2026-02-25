<?php

namespace Tests\Unit\Enums;

use App\Enums\SlotType;
use PHPUnit\Framework\TestCase;

class SlotTypeEnumTest extends TestCase
{
    public function test_all_slot_types_have_labels(): void
    {
        foreach (SlotType::cases() as $type) {
            $this->assertNotEmpty($type->label());
        }
    }

    public function test_core_systems_are_correct(): void
    {
        $coreTypes = [
            SlotType::ENGINE,
            SlotType::REACTOR,
            SlotType::HULL_PLATING,
            SlotType::SHIELD_GENERATOR,
            SlotType::SENSOR_ARRAY,
            SlotType::CARGO_MODULE,
        ];

        foreach ($coreTypes as $type) {
            $this->assertTrue($type->isCoreSystem(), "{$type->value} should be a core system");
            $this->assertFalse($type->isMultiSlot(), "{$type->value} should not be multi-slot");
        }
    }

    public function test_multi_slot_types_are_correct(): void
    {
        $multiTypes = [SlotType::WEAPON, SlotType::UTILITY];

        foreach ($multiTypes as $type) {
            $this->assertFalse($type->isCoreSystem(), "{$type->value} should not be a core system");
            $this->assertTrue($type->isMultiSlot(), "{$type->value} should be multi-slot");
        }
    }

    public function test_slot_columns_are_unique(): void
    {
        $columns = array_map(fn ($t) => $t->slotColumn(), SlotType::cases());
        $this->assertCount(count(SlotType::cases()), array_unique($columns));
    }

    public function test_there_are_exactly_eight_slot_types(): void
    {
        $this->assertCount(8, SlotType::cases());
    }

    public function test_slot_type_values_are_snake_case(): void
    {
        foreach (SlotType::cases() as $type) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $type->value);
        }
    }
}
