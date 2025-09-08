<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\View\Locator;

use MyBB\View\Locator\Exception as LocatorException;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\ResourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locator::class)]
#[CoversClass(StaticLocator::class)]
#[CoversClass(ViewletLocator::class)]
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
                ViewletLocator::composeString([
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                '@frontend/styles/main/header.css',
            ],
            [
                ViewletLocator::composeString([
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'filename' => 'header.css',
                ]),
                '@frontend/styles/header.css',
            ],
            [
                ViewletLocator::composeString([
                    'type' => ResourceType::STYLE,
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                'styles/main/header.css',
            ],
            [
                ViewletLocator::composeString([
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                '@frontend/main/header.css',
            ],
            [
                ViewletLocator::composeString([
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),
                'main/header.css',
            ],
        ];
    }

    #[DataProvider('composeStringCases')]
    public function testComposeString(string $string, string $expectedString): void
    {
        self::assertSame($expectedString, $string);
    }


    /**
     * @param class-string<Locator> $class
     * @param class-string<LocatorException> $expectedException
     */
    #[TestWith([
        StaticLocator::class,
        [
            'path' => null,
        ],
        LocatorException::class,
    ])]
    public function testComposeStringException(string $class, array $components, string $expectedException): void
    {
        self::expectException($expectedException);

        $class::composeString($components);
    }


    public static function getStringCases(): array
    {
        return [
            [
                new ViewletLocator([
                    'type' => ResourceType::STYLE,
                    'namespace' => null,
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),

                'directives' => [],
                'context' => [
                    'namespace' => 'frontend',
                ],

                'expectedString' => '@frontend/styles/main/header.css',
            ],
            [
                new ViewletLocator([
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),

                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                ],
                'context' => [
                    'namespace' => 'frontend',
                ],

                'expectedString' => 'styles/main/header.css',
            ],
            [
                new ViewletLocator([
                    'type' => ResourceType::STYLE,
                    'namespace' => 'parser',
                    'group' => 'main',
                    'filename' => 'header.css',
                ]),

                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [
                    'namespace' => 'frontend',
                ],

                'expectedString' => '@parser/styles/main/header.css',
            ],
        ];
    }

    #[DataProvider('getStringCases')]
    public function testGetString(Locator $locator, array $directives, array $context, string $expectedString): void
    {
        $string = $locator->getString($directives, $context);

        self::assertSame($expectedString, $string);
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

                'expectedClass' => ViewletLocator::class,
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
                    'type' => ViewletLocator::COMPONENT_UNSET,
                ],
                'context' => [],

                'expectedClass' => ViewletLocator::class,
                'expectedProperties' => [
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                'styles/main/header.css',
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                ],
                'context' => [],

                'expectedClass' => ViewletLocator::class,
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
                    'type' => ViewletLocator::COMPONENT_UNSET,
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                ],
                'context' => [],

                'expectedClass' => ViewletLocator::class,
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
                    'type' => ViewletLocator::COMPONENT_SET,
                    'namespace' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                ],

                'expectedClass' => ViewletLocator::class,
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
                    'type' => ViewletLocator::COMPONENT_UNSET,
                    'namespace' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                ],

                'expectedClass' => ViewletLocator::class,
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
                    'namespace' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [],

                'expectedClass' => ViewletLocator::class,
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

        self::assertInstanceOf($expectedClass, $locator);

        foreach ($expectedProperties as $name => $value) {
            self::assertSame($value, $locator->$name);
        }

        foreach ($expectedReturn as $method => $expected) {
            self::assertSame($expected, $locator->$method());
        }
    }


    public static function fromStringExceptionCases(): array
    {
        return [
            [
                'styles/main/header.css',
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_SET,
                ],
            ],
            [
                '@frontend/styles/main/header.css',
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                ],
            ],
            [
                'styles/main/header.css',
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [],
            ],
        ];
    }

    #[DataProvider('fromStringExceptionCases')]
    public function testFromStringException(
        string $string,
        array $directives,
        array $context = [],
    ): void {
        self::expectException(LocatorException::class);

        ViewletLocator::fromString($string, $directives, $context);
    }


    public static function getStringExceptionCases(): array
    {
        return [
            [
                new ViewletLocator([
                    'type' => ResourceType::STYLE,
                    'namespace' => null,
                    'group' => null,
                    'filename' => 'test.css',
                ]),
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_SET,
                ],
            ],

            [
                new ViewletLocator([
                    'type' => null,
                    'namespace' => 'frontend',
                    'group' => null,
                    'filename' => 'test.css',
                ]),
                'directives' => [
                    'type' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [],
            ],

            [
                new ViewletLocator([
                    'type' => null,
                    'namespace' => null,
                    'group' => null,
                    'filename' => null,
                ]),
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                    'type' => ViewletLocator::COMPONENT_UNSET,
                ],
            ],
            [
                new ViewletLocator([
                    'type' => null,
                    'namespace' => null,
                    'group' => null,
                    'filename' => '',
                ]),
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                    'type' => ViewletLocator::COMPONENT_UNSET,
                ],
            ],
            [
                new ViewletLocator([
                    'type' => null,
                    'namespace' => null,
                    'group' => null,
                    'filename' => null,
                ]),
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                    'type' => ViewletLocator::COMPONENT_UNSET,
                ],
                'context' => [
                    'filename' => null,
                ],
            ],
            [
                new ViewletLocator([
                    'type' => null,
                    'namespace' => null,
                    'group' => null,
                    'filename' => null,
                ]),
                'directives' => [
                    'namespace' => ViewletLocator::COMPONENT_UNSET,
                    'type' => ViewletLocator::COMPONENT_CONTEXT,
                ],
                'context' => [
                    'filename' => '',
                ],
            ],
        ];
    }

    #[DataProvider('getStringExceptionCases')]
    public function testGetStringException(Locator $locator, array $directives, array $context = []): void
    {
        self::expectException(LocatorException::class);

        $locator->getString($directives, $context);
    }
}
