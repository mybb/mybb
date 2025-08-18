<?php

namespace MyBB\Tests\Unit\View;

use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\ResourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocatorTest extends TestCase
{
    public static function composeStringCases(): array
    {
        return [
            [
                StaticLocator::composeString([
                    'path' => 'general.js',
                ]),
                './general.js',
            ],
            [
                ThemeletLocator::composeString([
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                '@frontend/styles/main/header.css',
            ],
            [
                ThemeletLocator::composeString([
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'filename' => 'header.css',
                ]),
                '@frontend/styles/header.css',
            ],
            [
                ThemeletLocator::composeString([
                    'type' => ResourceType::STYLE,
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                'styles/main/header.css',
            ],
            [
                ThemeletLocator::composeString([
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                '@frontend/main/header.css',
            ],
            [
                ThemeletLocator::composeString([
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                'main/header.css',
            ],
        ];
    }

    #[DataProvider('composeStringCases')]
    public function testComposeString(string $string, string $expectedString)
    {
        $this->assertSame($expectedString, $string);
    }

    public static function fromStringCases(): array
    {
        return [
            [
                '/a/b/c.css',
                'directives' => [],
                'context' => [],

                'expectedClass' => StaticLocator::class,
                'expectedProperties' => [
                    'path' => '/a/b/c.css',
                ],
                'expectedReturn' => [
                    'isRemote' => false,
                    'isCurrentDirectoryRelative' => false,
                ],
            ],
            [
                './a/b.css',
                'directives' => [],
                'context' => [],

                'expectedClass' => StaticLocator::class,
                'expectedProperties' => [
                    'path' => './a/b.css',
                ],
                'expectedReturn' => [
                    'isRemote' => false,
                    'isCurrentDirectoryRelative' => true,
                ],
            ],
            [
                '../a/b.css',
                'directives' => [],
                'context' => [],

                'expectedClass' => StaticLocator::class,
                'expectedProperties' => [
                    'path' => '../a/b.css',
                ],
                'expectedReturn' => [
                    'isRemote' => false,
                    'isCurrentDirectoryRelative' => false,
                ],
            ],
            [
                'https://example.com/style.css',
                'directives' => [],
                'context' => [],

                'expectedClass' => StaticLocator::class,
                'expectedProperties' => [
                    'path' => 'https://example.com/style.css',
                ],
                'expectedReturn' => [
                    'isRemote' => true,
                    'isCurrentDirectoryRelative' => false,
                ],
            ],
            [
                '//example.com/style.css',
                'directives' => [],
                'context' => [],

                'expectedClass' => StaticLocator::class,
                'expectedProperties' => [
                    'path' => '//example.com/style.css',
                ],
                'expectedReturn' => [
                    'isRemote' => true,
                    'isCurrentDirectoryRelative' => false,
                ],
            ],

            [
                '@frontend/styles/main/header.css',
                'directives' => [],
                'context' => [],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                '@frontend/main/header.css',
                'directives' => [
                    'type' => ThemeletLocator::COMPONENT_UNSET,
                ],
                'context' => [],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                'styles/main/header.css',
                'directives' => [
                    'namespace' => ThemeletLocator::COMPONENT_UNSET,
                ],
                'context' => [],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => null,
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                'main/header.css',
                'directives' => [
                    'type' => ThemeletLocator::COMPONENT_UNSET,
                    'namespace' => ThemeletLocator::COMPONENT_UNSET,
                ],
                'context' => [],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'type' => null,
                    'namespace' => null,
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                'styles/main/header.css',
                'directives' => [
                    'type' => ThemeletLocator::COMPONENT_SET,
                    'namespace' => ThemeletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                ],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                '@parser/main/header.css',
                'directives' => [
                    'type' => ThemeletLocator::COMPONENT_UNSET,
                    'namespace' => ThemeletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                ],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'parser',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                '@frontend/styles/main/header.css',
                'directives' => [
                    'namespace' => ThemeletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [],

                'expectedClass' => ThemeletLocator::class,
                'expectedProperties' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
        ];
    }

    #[DataProvider('fromStringCases')]
    public function testFromString(
        string $string,
        array $directives,
        array $context,
        string $expectedClass,
        array $expectedProperties,
        array $expectedReturn = [],
    ): void {
        $locator = Locator::fromString($string, $directives, $context);

        $this->assertInstanceOf($expectedClass, $locator);

        foreach ($expectedProperties as $name => $value) {
            $this->assertSame($value, $locator->$name);
        }

        foreach ($expectedReturn as $method => $expected) {
            self::assertSame($expected, $locator->$method());
        }
    }
}
