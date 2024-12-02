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

    public static function decomposeStringCases(): array
    {
        return [
            [
                '/a/b/c.css',
                [],
                StaticLocator::class,
                [
                    'path' => '/a/b/c.css',
                ],
            ],
            [
                '@frontend/styles/main/header.css',
                [],
                ThemeletLocator::class,
                [
                    'type' => ResourceType::STYLE,
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                '@frontend/main/header.css',
                [
                    'type' => ThemeletLocator::COMPONENT_UNSET,
                ],
                ThemeletLocator::class,
                [
                    'namespace' => 'frontend',
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                'styles/main/header.css',
                [
                    'namespace' => ThemeletLocator::COMPONENT_UNSET,
                ],
                ThemeletLocator::class,
                [
                    'type' => ResourceType::STYLE,
                    'namespace' => null,
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
            [
                'main/header.css',
                [
                    'type' => ThemeletLocator::COMPONENT_UNSET,
                    'namespace' => ThemeletLocator::COMPONENT_UNSET,
                ],
                ThemeletLocator::class,
                [
                    'type' => null,
                    'namespace' => null,
                    'group' => 'main',
                    'filename' => 'header.css',
                ],
            ],
        ];
    }

    #[DataProvider('decomposeStringCases')]
    public function testDecomposeString(string $string, array $directives, string $expectedClass, array $expectedProperties)
    {
        $locator = Locator::fromString($string, $directives);

        $this->assertInstanceOf($expectedClass, $locator);

        foreach ($expectedProperties as $name => $value) {
            $this->assertSame($value, $locator->$name);
        }
    }
}
