<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Extensions\Theme;

use Illuminate\Filesystem\Filesystem;
use Mockery;
use MyBB\Extensions\Exception as ExtensionException;
use MyBB\Extensions\Extension;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\Extensions\Theme\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Theme::class)]
final class ThemeTest extends TestCase
{
    #[TestWith(['a'])]
    #[TestWith(['abc'])]
    #[TestWith(['abc_abc'])]
    #[TestWith(['core.a'])]
    #[TestWith(['core.abc'])]
    #[TestWith(['core.abc_abc'])]
    #[TestWith(['theme.1'])]
    #[TestWith(['theme.123'])]
    #[DoesNotPerformAssertions]
    public function testPackageNames(string $packageName): void
    {
        $repository = $this->getRepository([
            $packageName => [],
        ]);


        $repository->get($packageName);
    }


    #[TestWith(['123'])]
    #[TestWith(['a2c'])]
    #[TestWith(['abc.abc'])]
    #[TestWith(['theme.abc'])]
    #[TestWith(['theme.1_2'])]
    public function testInvalidPackageNames(string $packageName): void
    {
        $repository = $this->getRepository([
            $packageName => [],
        ]);


        self::expectException(ExtensionException::class);

        $repository->get($packageName);
    }


    public static function manifestCases(): array
    {
        return [
            [
                '_manifest' => [
                    'type' => 'mybb-theme',
                ],
            ],
            [
                '_manifest' => [
                    'version' => '1.2.3.A-b',
                ],
            ],
            [
                '_manifest' => [
                    'extra' => [
                        'abc' => 123,
                        'inherits' => [],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('manifestCases')]
    #[DoesNotPerformAssertions]
    public function testManifest(array $_manifest): void
    {
        $repository = $this->getRepository([
            'abc' => [
                '_manifest' => $_manifest,
            ],
        ]);

        $extension = $repository->get('abc');


        $extension->getManifest();
    }


    public static function invalidManifestCases(): array
    {
        return [
            [
                '_manifest' => [
                    'type' => 'abc',
                ],
            ],
            [
                '_manifest' => [
                    'type' => 'mybb-plugin',
                ],
            ],
            [
                '_manifest' => [
                    'version' => '?',
                ],
            ],
            [
                '_manifest' => [
                    'extra' => [
                        'inherits' => 'abc',
                    ],
                ],
            ],
            [
                '_manifest' => [
                    'extra' => [
                        'inherits' => 123,
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('invalidManifestCases')]
    public function testInvalidManifest(array $_manifest): void
    {
        $repository = $this->getRepository([
            'abc' => [
                '_manifest' => $_manifest,
            ],
        ]);

        $extension = $repository->get('abc');


        self::expectException(ExtensionException::class);

        $extension->getManifest();
    }


    public static function hierarchyCases(): array
    {
        return [
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [],
                            ],
                        ],
                    ],
                    'b' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['a'],
                            ],
                        ],
                    ],
                    'c' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['a', 'b'],
                            ],
                        ],
                    ],
                    'd' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['c', 'a'],
                            ],
                        ],
                    ],
                    'e' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['a', 'd', 'b'],
                            ],
                        ],
                    ],
                ],
                'expected' => [
                    'a' => [
                        'getInheritanceChain' => ['a'],
                        'getAncestors' => [],
                        'getDescendants' => ['b', 'c', 'd', 'e'],
                        'getExtensionsDeclaringAsAncestor' => ['b', 'c', 'd', 'e'],
                    ],
                    'b' => [
                        'getInheritanceChain' => ['b', 'a'],
                        'getAncestors' => ['a'],
                        'getDescendants' => ['c', 'd', 'e'],
                        'getExtensionsDeclaringAsAncestor' => ['c', 'e'],
                    ],
                    'c' => [
                        'getInheritanceChain' => ['c', 'a', 'b'],
                        'getAncestors' => ['a', 'b'],
                        'getDescendants' => ['d', 'e'],
                        'getExtensionsDeclaringAsAncestor' => ['d'],
                    ],
                    'd' => [
                        'getInheritanceChain' => ['d', 'c', 'b', 'a'],
                        'getAncestors' => ['c', 'b', 'a'],
                        'getDescendants' => ['e'],
                        'getExtensionsDeclaringAsAncestor' => ['e'],
                    ],
                    'e' => [
                        'getInheritanceChain' => ['e', 'a', 'd', 'c', 'b'],
                        'getAncestors' => ['a', 'd', 'c', 'b'],
                        'getDescendants' => [],
                        'getExtensionsDeclaringAsAncestor' => [],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('hierarchyCases')]
    public function testHierarchy(array $_extensions, array $expected): void
    {
        $repository = $this->getRepository($_extensions);

        foreach ($expected as $extensionName => $assertions) {
            $extension = $repository->get($extensionName);

            foreach ($assertions as $methodName => $expectedValue) {
                $result = $extension->$methodName($repository);

                self::assertSame(
                    $expectedValue,
                    array_keys($result),
                    $methodName . '() for Extension ' . $extensionName,
                );
            }
        }
    }


    public static function invalidHierarchyCases(): array
    {
        return [
            // direct circular inheritance
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['a'],
                            ],
                        ],
                    ],
                ],
                '_extension' => 'a',
            ],

            // indirect circular inheritance
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['b'],
                            ],
                        ],
                    ],
                    'b' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['a'],
                            ],
                        ],
                    ],
                ],
                '_extension' => 'a',
            ],

            // missing ancestor
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => ['x'],
                            ],
                        ],
                    ],
                ],
                '_extension' => 'a',
            ],

            // non-string value
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [0],
                            ],
                        ],
                    ],
                ],
                '_extension' => 'a',
            ],

            // explicit integer key
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [
                                    1 => 'b',
                                ],
                            ],
                        ],
                    ],
                    'b' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [],
                            ],
                        ],
                    ],
                ],
                '_extension' => 'a',
            ],

            // duplicate ancestor declaration
            [
                '_extensions' => [
                    'a' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [
                                    'b',
                                    'b',
                                ],
                            ],
                        ],
                    ],
                    'b' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [],
                            ],
                        ],
                    ],
                ],
                '_extension' => 'a',
            ],

            // illegal type hierarchy
            [
                '_extensions' => [
                    'core.abc' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [
                                    'abc',
                                ],
                            ],
                        ],
                    ],
                    'abc' => [],
                ],
                '_extension' => 'core.abc',
            ],
            [
                '_extensions' => [
                    'abc' => [
                        '_manifest' => [
                            'extra' => [
                                'inherits' => [
                                    'theme.123',
                                ],
                            ],
                        ],
                    ],
                    'theme.123' => [],
                ],
                '_extension' => 'abc',
            ],
        ];
    }

    #[DataProvider('invalidHierarchyCases')]
    public function testInvalidHierarchy(array $_extensions, string $_extension): void
    {
        $repository = $this->getRepository($_extensions);

        $theme = $repository->get($_extension);


        self::expectException(ExtensionException::class);

        $theme->getInheritanceChain($repository);
    }

    protected function setUp(): void
    {
        if (!defined('MYBB_ROOT')) {
            define('MYBB_ROOT', __DIR__ . '/../../../../');
        }
    }

    private function getRepository(array $_extensions): ThemeRepository
    {
        $filesystemStub = Mockery::mock(Filesystem::class)->makePartial();

        $filesystemStub
            ->shouldReceive('directories')
            ->with(ThemeRepository::getAbsoluteBasePath())
            ->andReturn(
                array_keys($_extensions)
            );

        foreach ($_extensions as $name => $_extension) {
            $filesystemStub
                ->shouldReceive('isDirectory')
                ->with(ThemeRepository::getAbsoluteBasePath() . '/' . $name)
                ->andReturn(true);

            if (isset($_extension['_manifest'])) {
                $filePath = ThemeRepository::getAbsoluteBasePath() . '/' . $name . '/' . Extension::MANIFEST_FILE_PATH;

                $filesystemStub
                    ->shouldReceive('isFile')
                    ->with($filePath)
                    ->andReturn(true);

                $filesystemStub
                    ->shouldReceive('get')
                    ->with($filePath)
                    ->andReturn(
                        json_encode($_extension['_manifest'])
                    );
            }
        }

        return new ThemeRepository($filesystemStub);
    }
}
