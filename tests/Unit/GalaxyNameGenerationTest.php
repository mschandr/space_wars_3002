<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Faker\Providers\GalaxyNameProvider;

class GalaxyNameGenerationTest extends TestCase
{
    #[Test]
    public function it_generates_a_non_empty_string(): void
    {
        $name = GalaxyNameProvider::generateGalaxyName();

        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    #[Test]
    public function it_generates_varied_names(): void
    {
        $names = [];
        for ($i = 0; $i < 20; $i++) {
            $names[] = GalaxyNameProvider::generateGalaxyName();
        }

        $uniqueCount = count(array_unique($names));
        $this->assertGreaterThan(10, $uniqueCount, 'Generator produced too many duplicates.');
    }
}
