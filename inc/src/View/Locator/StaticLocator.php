<?php

declare(strict_types=1);

namespace MyBB\View\Locator;

use Exception;

/**
 * A reference to an Asset with a static path (outside any Themelet structure).
 */
class StaticLocator extends Locator
{
    private const URI_SCHEME_SUFFIX = ':';
    private const URI_AUTHORITY_PREFIX = '//';

    private const PATH_ABSOLUTE_PREFIX = '/';
    private const PATH_RELATIVE_CURRENT_DIRECTORY_PREFIX = './';
    private const PATH_RELATIVE_PARENT_DIRECTORY_PREFIX = '../';

    public readonly ?string $path;

    public function __construct(array $components = [])
    {
        foreach ($components as $component => $value) {
            $this->$component = $value;
        }
    }

    public static function fromString(string $string, array $directives = [], array $context = []): static
    {
        $components = static::decomposeString($string);

        $locator = new static();

        foreach ($components as $component => $value) {
            $locator->$component = $value;
        }

        return $locator;
    }

    /**
     * @param array{
     *   path?: ?string,
     * } $components
     */
    public static function composeString(array $components): string
    {
        if ($components['path'] !== null) {
            $string = '';

            if (self::isImplicitCurrentDirectoryRelativePath($components['path'])) {
                $string .= self::PATH_RELATIVE_CURRENT_DIRECTORY_PREFIX;
            }

            $string .= $components['path'];

            return $string;
        } else {
            throw new Exception('Missing path for Locator');
        }
    }

    /**
     * @return array{
     *   path: ?string,
     * }
     */
    public static function decomposeString(string $string): array
    {
        return [
            'path' => $string,
        ];
    }

    public static function isStaticLocator(string $locator): bool
    {
        return self::isExplicitDirectoryPath($locator) || self::isRemoteLocator($locator);
    }

    private static function isRemoteLocator(string $locator): bool
    {
        return (
            str_starts_with($locator, self::URI_AUTHORITY_PREFIX) || // network-path / scheme-relative URI
            str_contains($locator, self::URI_SCHEME_SUFFIX . self::URI_AUTHORITY_PREFIX) // absolute URI
        );
    }

    private static function isExplicitDirectoryPath(string $locator): bool
    {
        return (
            str_starts_with($locator, self::PATH_ABSOLUTE_PREFIX) ||
            str_starts_with($locator, self::PATH_RELATIVE_CURRENT_DIRECTORY_PREFIX) ||
            str_starts_with($locator, self::PATH_RELATIVE_PARENT_DIRECTORY_PREFIX)
        );
    }

    private static function isCurrentDirectoryRelativePath(string $locator): bool
    {
        return (
            str_starts_with($locator, self::PATH_RELATIVE_CURRENT_DIRECTORY_PREFIX) ||
            self::isImplicitCurrentDirectoryRelativePath($locator)
        );
    }

    private static function isImplicitCurrentDirectoryRelativePath(string $locator): bool
    {
        return (
            !self::isRemoteLocator($locator) &&
            !self::isExplicitDirectoryPath($locator)
        );
    }

    public function getString(array $directives = [], array $context = []): string
    {
        $information = [];

        if ($this->path !== null) {
            $information['path'] = $this->path;
        } else {
            throw new Exception('Missing path for Locator');
        }

        return self::composeString($information);
    }

    public function getNamespaceRelativeIdentifier(): string
    {
        // no transformations needed for Static Locators
        return $this->getString();
    }

    public function isCurrentDirectoryRelative(): bool
    {
        return self::isCurrentDirectoryRelativePath(
            $this->getPath()
        );
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function isRemote(): bool
    {
        return self::isRemoteLocator(
            $this->getPath()
        );
    }
}
