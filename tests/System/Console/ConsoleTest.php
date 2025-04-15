<?php

declare(strict_types=1);

namespace MyBB\Tests\System\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

#[Large]
class ConsoleTest extends TestCase
{
    public static function statusCommandCases(): array
    {
        return [
            [
                'input' => 'bin/cli status',
                'validator' => self::assertIsNotNumeric(...),
            ],
        ];
    }

    #[RequiresFunction('exec')]
    #[DataProvider('statusCommandCases')]
    public function testStatusCommand(string $input, callable $validator): void
    {
        chdir(MYBB_ROOT);

        exec($input, $output, $resultCode);

        self::assertSame($resultCode, 0);

        $output = implode("\n", $output);

        $validator($output);
    }
}
