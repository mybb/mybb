<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities;

use InvalidArgumentException;
use MyBB\Utilities\Arrays;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arrays::class)]
final class ArraysTest extends TestCase
{
    public static function getNestedCases(): array
    {
        return [
            [
                'array' => [],
                'path' => [],
                'expected' => [],
            ],
            [
                'array' => [],
                'path' => ['a'],
                'expected' => null,
            ],

            [
                'array' => ['a' => 123],
                'path' => [],
                'expected' => ['a' => 123],
            ],
            [
                'array' => ['a' => 123],
                'path' => ['a'],
                'expected' => 123,
            ],
            [
                'array' => ['a' => 123],
                'path' => ['x'],
                'expected' => null,
            ],

            [
                'array' => ['a' => ['b' => 123]],
                'path' => [],
                'expected' => ['a' => ['b' => 123]],
            ],
            [
                'array' => ['a' => ['b' => 123]],
                'path' => ['a'],
                'expected' => ['b' => 123],
            ],
            [
                'array' => ['a' => ['b' => 123]],
                'path' => ['a', 'b'],
                'expected' => 123,
            ],
            [
                'array' => ['a' => ['b' => 123]],
                'path' => ['a', 'x'],
                'expected' => null,
            ],
            [
                'array' => ['a' => ['b' => 123]],
                'path' => ['a', 'b', 'c'],
                'expected' => null,
            ],

            [
                'array' => ['a' => ['b' => null]],
                'path' => ['a', 'b'],
                'expected' => null,
            ],

            [
                'array' => [1 => [0 => [1 => 123]]],
                'path' => [1, 0],
                'expected' => [1 => 123],
            ],
            [
                'array' => [1 => [0 => [1 => 123]]],
                'path' => [1, 0, 1, 0],
                'expected' => null,
            ],

            [
                'array' => [PHP_INT_MAX => [PHP_INT_MAX => PHP_INT_MAX]],
                'path' => [PHP_INT_MAX, PHP_INT_MAX],
                'expected' => PHP_INT_MAX,
            ],
        ];
    }

    #[DataProvider('getNestedCases')]
    public function testGetNested(array $array, array $path, mixed $expected): void
    {
        $actual = Arrays::getNested($array, $path);

        self::assertSame($expected, $actual);
    }


    public static function setNestedCases(): array
    {
        return [
            [
                'array' => [],
                'setNested' => [
                    [[], [123]],
                ],
                'expected' => [123],
            ],
            [
                'array' => [],
                'setNested' => [
                    [['a', 'b', 'c'], 123],
                ],
                'expected' => ['a' => ['b' => ['c' => 123]]],
            ],
            [
                'array' => ['a' => 123],
                'setNested' => [
                    [['a', 'b'], 123],
                ],
                'expected' => ['a' => ['b' => 123]],
            ],
            [
                'array' => ['a' => []],
                'setNested' => [
                    [['a', 'b'], 123],
                ],
                'expected' => ['a' => ['b' => 123]],
            ],
            [
                'array' => ['a' => ['b' => 123]],
                'setNested' => [
                    [['a', 'b'], 456],
                ],
                'expected' => ['a' => ['b' => 456]],
            ],
            [
                'array' => ['a' => ['b' => ['b.1' => 123, 'b.2' => 123]]],
                'setNested' => [
                    [['a', 'b', 'b.1'], 456],
                ],
                'expected' => ['a' => ['b' => ['b.1' => 456, 'b.2' => 123]]],
            ],
            [
                'array' => ['a' => ['b' => ['b.1' => 123], 'c' => 123]],
                'setNested' => [
                    [['a', 'b', 'b.1'], 456],
                ],
                'expected' => ['a' => ['b' => ['b.1' => 456], 'c' => 123]],
            ],
            [
                'array' => [],
                'setNested' => [
                    [['a'], [456]],
                    [['b'], []],
                    [['a'], PHP_INT_MAX],
                    [['b'], [0, false, null]],
                    [['b', 'b.2', 'b.2.1'], null],
                    [['c'], null],
                    [['c'], false],
                    [['d'], [PHP_INT_MAX => false]],
                    [['d', PHP_INT_MAX], true],
                ],
                'expected' => [
                    'a' => PHP_INT_MAX,
                    'b' => [
                        0 => 0,
                        1 => false,
                        2 => null,
                        'b.2' => [
                            'b.2.1' => null,
                        ],
                    ],
                    'c' => false,
                    'd' => [
                        PHP_INT_MAX => true,
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('setNestedCases')]
    public function testSetNested(array $array, array $setNested, mixed $expected): void
    {
        foreach ($setNested as [$path, $value]) {
            Arrays::setNested($array, $path, $value);
        }

        self::assertSame($expected, $array);
    }


    public static function invalidSetNestedCases(): array
    {
        $fixture = [
            'array' => [],
            'path' => [],
        ];

        return [
            [
                ...$fixture,
                'value' => 123,
            ],
            [
                ...$fixture,
                'value' => null,
            ],
            [
                ...$fixture,
                'value' => false,
            ],
            [
                ...$fixture,
                'value' => true,
            ],
        ];
    }

    #[DataProvider('invalidSetNestedCases')]
    public function testInvalidSetNested(array $array, array $path, mixed $value): void
    {
        self::expectException(InvalidArgumentException::class);

        Arrays::setNested($array, $path, $value);
    }


    public static function deleteNestedCases(): array
    {
        return [
            [
                'array' => [],
                'deleteNested' => [
                    [[], false],
                ],
                'expected' => [],
            ],
            [
                'array' => [],
                'deleteNested' => [
                    [[0], false],
                ],
                'expected' => [],
            ],
            [
                'array' => ['a' => []],
                'deleteNested' => [
                    [[], true],
                ],
                'expected' => [],
            ],

            [
                'array' => ['a' => ['b' => []]],
                'deleteNested' => [
                    [['a', 'b'], true],
                ],
                'expected' => ['a' => []],
            ],
            [
                'array' => ['a' => ['b' => 123]],
                'deleteNested' => [
                    [['a', 'b', 'c'], false],
                ],
                'expected' => ['a' => ['b' => 123]],
            ],
            [
                'array' => ['a' => ['b' => ['c' => null]]],
                'deleteNested' => [
                    [['a', 'b', 'x'], false],
                ],
                'expected' => ['a' => ['b' => ['c' => null]]],
            ],
            [
                'array' => ['a' => ['b' => []]],
                'deleteNested' => [
                    [['a', 'b', 'c'], false],
                ],
                'expected' => ['a' => ['b' => []]],
            ],

            [
                'array' => [0 => [PHP_INT_MAX => 123, 2 => PHP_INT_MAX]],
                'deleteNested' => [
                    [[0, PHP_INT_MAX], true],
                ],
                'expected' => [0 => [2 => PHP_INT_MAX]],
            ],

            [
                'array' => ['a' => 1, 'b' => 2, 'c' => ['d' => 3, 'e' => 4]],
                'deleteNested' => [
                    [['a'], true],
                    [['c', 'd'], true],
                    [['x'], false],
                ],
                'expected' => ['b' => 2, 'c' => ['e' => 4]],
            ],
        ];
    }

    #[DataProvider('deleteNestedCases')]
    public function testDeleteNested(array $array, array $deleteNested, array $expected): void
    {
        foreach ($deleteNested as [$path, $expectedResult]) {
            $result = Arrays::deleteNested($array, $path);
            self::assertSame($expectedResult, $result);
        }

        self::assertSame($expected, $array);
    }
}
