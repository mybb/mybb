<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration;
}
