<?php

namespace MyBB\Tests\Unit;

use MyBB\Tests\Traits\LegacyCoreAwareTest;

class FunctionsTest extends TestCase
{
    use LegacyCoreAwareTest;

    public static function setUpBeforeClass(): void
    {
        static::setupMybb();
    }

    public function testFunctionsMySetTimeLimit()
    {
        $this->assertTrue(my_set_time_limit());
    }
}
