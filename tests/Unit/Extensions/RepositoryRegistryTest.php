<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Extensions;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Mockery;
use MyBB\Extensions\Exception as ExtensionException;
use MyBB\Extensions\Extension;
use MyBB\Extensions\Plugin\Plugin;
use MyBB\Extensions\Plugin\Repository as PluginRepository;
use MyBB\Extensions\RepositoryRegistry;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\Extensions\Theme\Theme;
use MyBB\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;

#[CoversClass(RepositoryRegistry::class)]
final class RepositoryRegistryTest extends TestCase
{
    #[TestWith([Theme::class, ThemeRepository::class])]
    #[TestWith([Plugin::class, PluginRepository::class])]
    public function testGetRepositoryForExtensionClass(string $extensionClass, string $expectedClass): void
    {
        $registry = $this->getRepositoryRegistry();


        $repository = $registry->getRepositoryForExtensionClass($extensionClass);


        self::assertInstanceOf($expectedClass, $repository);
    }

    public function testGetRepositoryForUnknownExtensionClass(): void
    {
        $registry = $this->getRepositoryRegistry();


        self::expectException(InvalidArgumentException::class);


        $registry->getRepositoryForExtensionClass(Extension::class);
    }

    #[TestWith(['inc/themes/abc/file.php', ThemeRepository::class])]
    #[TestWith(['inc/plugins/abc.php', PluginRepository::class])]
    public function testGetRepositoryFromAbsoluteBasePath(string $path, string $expectedClass): void
    {
        $registry = $this->getRepositoryRegistry();


        $repository = $registry->getRepositoryFromAbsoluteBasePath(MYBB_ROOT . $path);


        self::assertInstanceOf($expectedClass, $repository);
    }

    public function testGetRepositoryFromUnknownAbsoluteBasePath(): void
    {
        $registry = $this->getRepositoryRegistry();


        self::expectException(InvalidArgumentException::class);


        $registry->getRepositoryFromAbsoluteBasePath(MYBB_ROOT . 'unknown/abc');
    }

    #[TestWith(['inc/themes/abc/file.php', ThemeRepository::class])]
    #[TestWith(['inc/plugins/abc.php', PluginRepository::class])]
    public function testGetRepositoryFromBasePath(string $path, string $expectedClass): void
    {
        $registry = $this->getRepositoryRegistry();


        $repository = $registry->getRepositoryFromBasePath($path);


        self::assertInstanceOf($expectedClass, $repository);
    }

    #[TestWith([
        'mockExtensions' => [
            Theme::class => ['abc'],
        ],
        'packageName' => 'abc',
        'expectedClass' => Theme::class,
    ])]
    #[TestWith([
        'mockExtensions' => [
            Plugin::class => ['abc'],
        ],
        'packageName' => 'abc',
        'expectedClass' => Plugin::class,
    ])]
    #[TestWith([
        'mockExtensions' => [],
        'packageName' => 'abc',
        'expectedClass' => null,
    ])]
    public function testGetExistingExtensionFromPackageName(
        array $mockExtensions,
        string $packageName,
        ?string $expectedClass,
    ): void {
        $registry = $this->getRepositoryRegistry($mockExtensions);


        $extension = $registry->getExistingExtensionFromPackageName($packageName);


        if ($expectedClass === null) {
            self::assertNull($extension);
        } else {
            self::assertInstanceOf($expectedClass, $extension);
        }
    }

    public function testGetExistingExtensionFromDuplicatePackageName(): void
    {
        self::expectException(ExtensionException::class);


        $registry = $this->getRepositoryRegistry([
            Theme::class => ['abc'],
            Plugin::class => ['abc'],
        ]);

        $registry->getExistingExtensionFromPackageName('abc');
    }

    #[TestWith(['inc/themes/abc', Theme::class])]
    #[TestWith(['inc/themes/abc/', Theme::class])]
    #[TestWith(['inc/themes/abc/file', Theme::class])]
    #[TestWith(['inc/plugins/abc', Plugin::class])]
    #[TestWith(['inc/plugins/abc/', Plugin::class])]
    #[TestWith(['inc/plugins/abc/file', Plugin::class])]
    public function testGetExtensionFromPath(string $path, string $expectedClass): void
    {
        $registry = $this->getRepositoryRegistry();


        $extension = $registry->getExtensionFromPath(MYBB_ROOT . $path);

        self::assertInstanceOf($expectedClass, $extension);
        self::assertSame('abc', $extension->getPackageName());


        $extension = $registry->getExtensionFromAbsolutePath(MYBB_ROOT . $path);

        self::assertInstanceOf($expectedClass, $extension);
        self::assertSame('abc', $extension->getPackageName());
    }

    #[TestWith(['inc/themes'])]
    #[TestWith(['inc/plugins'])]
    public function testGetExtensionFromInvalidAbsolutePath(string $relativePath): void
    {
        $registry = $this->getRepositoryRegistry();


        self::expectException(InvalidArgumentException::class);


        $registry->getExtensionFromAbsolutePath(MYBB_ROOT . $relativePath);
    }

    public function testGetExtensionFromUnknownPath(): void
    {
        $registry = $this->getRepositoryRegistry();


        self::expectException(InvalidArgumentException::class);


        $registry->getExtensionFromPath('unknown/abc/file.php');
    }

    protected function setUp(): void
    {
        if (!defined('MYBB_ROOT')) {
            define('MYBB_ROOT', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);
        }
    }

    /**
     * @param array<class-string<Extension>, list<string>> $mockExtensions
     *   Package names of Extensions reported as existing via mocked filesystem, by Extension type class.
     */
    private function getRepositoryRegistry(array $mockExtensions = []): RepositoryRegistry
    {
        $filesystem = Mockery::mock(Filesystem::class);

        $filesystem->shouldReceive('isFile')->byDefault()->andReturn(false);
        $filesystem->shouldReceive('isDirectory')->byDefault()->andReturn(false);

        foreach ($mockExtensions as $extensionClass => $packageNames) {
            foreach ($packageNames as $packageName) {
                $path = $extensionClass::EXTENSION_TYPE_ABSOLUTE_BASE_PATH . '/' . $packageName;
                $method = 'isDirectory';

                if ($extensionClass === Plugin::class) {
                    // legacy format used in Plugin::exists()
                    $path .= '.php';
                    $method = 'isFile';
                }

                $filesystem->shouldReceive($method)->with($path)->andReturn(true);
            }
        }

        return new RepositoryRegistry([
            Theme::class => new ThemeRepository($filesystem),
            Plugin::class => new PluginRepository($filesystem),
        ]);
    }
}
