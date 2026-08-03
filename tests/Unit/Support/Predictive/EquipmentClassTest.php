<?php

namespace Tests\Unit\Support\Predictive;

use App\Support\Predictive\EquipmentClass;
use PHPUnit\Framework\TestCase;

class EquipmentClassTest extends TestCase
{
    public function test_resolves_tag_prefixes_and_spoken_names_to_the_same_class(): void
    {
        foreach (['SS', 'ss', 'scoop', 'Scooptram', 'LHD'] as $alias) {
            $this->assertSame('SCOOPTRAM', EquipmentClass::canonical($alias), "alias {$alias}");
        }

        $this->assertSame('CAMION_BAJO_PERFIL', EquipmentClass::canonical('VQ'));
        $this->assertSame('CAMION_BAJO_PERFIL', EquipmentClass::canonical('camión'));
        $this->assertSame('JUMBO', EquipmentClass::canonical('JB'));
        $this->assertSame('QUEBRADORA', EquipmentClass::canonical('trituradora'));
        $this->assertSame('BANDA_TRANSPORTADORA', EquipmentClass::canonical('banda'));
    }

    public function test_unknown_values_are_normalized_but_not_invented(): void
    {
        $this->assertSame('EQUIPO_RARO', EquipmentClass::canonical('equipo raro'));
        $this->assertNull(EquipmentClass::canonical(null));
        $this->assertNull(EquipmentClass::canonical('   '));
    }

    public function test_matches_compares_across_naming_forms(): void
    {
        $this->assertTrue(EquipmentClass::matches('SCOOPTRAM', 'SS'));
        $this->assertTrue(EquipmentClass::matches('SCOOPTRAM', 'scoop'));
        $this->assertFalse(EquipmentClass::matches('SCOOPTRAM', 'JUMBO'));
        $this->assertFalse(EquipmentClass::matches(null, 'JUMBO'));
    }

    public function test_in_list_accepts_any_alias_of_the_listed_classes(): void
    {
        $this->assertTrue(EquipmentClass::inList('SS', ['JUMBO', 'SCOOPTRAM']));
        $this->assertTrue(EquipmentClass::inList('SCOOPTRAM', ['ss']));
        $this->assertFalse(EquipmentClass::inList('MOLINO', ['JUMBO', 'SCOOPTRAM']));
    }
}
