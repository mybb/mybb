<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use Exception;
use InvalidArgumentException;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\Decorator\PublishableThemelet;
use MyBB\View\Themelet\ThemeletInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Path;

/**
 * An Asset created from a Themelet Resource.
 *
 * @property ThemeletLocator $locator
 */
class ThemeletAsset extends Asset
{
    public const WEB_ROOT_RELATIVE_BASE_PATH = 'cache/themelets/';
    public const ABSOLUTE_BASE_PATH = MYBB_ROOT . self::WEB_ROOT_RELATIVE_BASE_PATH;

    private Resource $resource;

    public function __construct(
        readonly protected ThemeletLocator $locator,
        readonly protected ThemeletInterface $themelet,
    )
    {
        if (!PublishableThemelet::decorates($themelet)) {
            throw new InvalidArgumentException('PublishableThemelet required for `' . static::class . '`');
        }
    }

    public function getLocator(): ThemeletLocator
    {
        return $this->locator;
    }

    /**
     * Return a server path to the generated static file.
     */
    public function getAbsolutePath(): string
    {
        return MYBB_ROOT . $this->getPublicPath();
    }

    /**
     * Return an HTTP-accessible path to the file.
     */
    public function getPublicPath(): string
    {
        return
            $this->themelet->getPublishingPath() .
            '/' .
            $this->locator->getNamespace() .
            '/' .
            $this->locator->getSubPath()
        ;
    }

    public function getUrl(bool $useCdn = true): string
    {
        $url = parent::getUrl();

        if ($time = filemtime($this->getAbsolutePath())) {
            $url .= '?t=' . $time;
        }

        return $url;
    }

    public function getType(): ResourceType
    {
        return $this->locator->type;
    }

    public function getNamespace(): string
    {
        return $this->locator->namespace;
    }

    public function getGroup(): string
    {
        return $this->locator->group;
    }

    public function getFilename(): string
    {
        return $this->locator->filename;
    }

    public function getSubPath(): string
    {
        return $this->locator->getSubPath();
    }

    public function getResource(): Resource
    {
        if (!isset($this->resource)) {
            $sourceLocatorString = $this->getProperties()['source'] ?? null;

            if ($sourceLocatorString !== null) {
                $resourceLocator = $this->locator->getSibling($sourceLocatorString);
            } else {
                $resourceLocator = $this->locator;
            }

            $this->resource = $this->getThemelet()->getResource($resourceLocator);
        }

        return $this->resource;
    }

    public function exists(): bool
    {
        return file_exists(
            $this->getAbsolutePath()
        );
    }

    public function write(string $content, $pointer = null): bool
    {
        $path = $this->getAbsolutePath();

        if (
            !Path::isBasePath(self::ABSOLUTE_BASE_PATH, $path) ||
            Path::hasExtension($path, 'php')
        ) {
            throw new Exception('Illegal write path `' . $path . '`');
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        if ($pointer !== null) {
            $fh = $pointer;
        } else {
            $fh = fopen($path, 'c');

            if ($fh === false) {
                throw new RuntimeException('Failed to open `' . $path . '`');
            }

            if (!flock($fh, LOCK_EX)) {
                throw new RuntimeException('Failed to acquire exclusive lock for `' . $path . '`');
            }
        }

        $result = fwrite($fh, $content) !== false;

        if ($pointer === null) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $result;
    }

    public function delete(): bool
    {
        $path = $this->getAbsolutePath();

        if (
            !Path::isBasePath(self::ABSOLUTE_BASE_PATH, $path) ||
            Path::hasExtension($path, 'php')
        ) {
            throw new Exception('Illegal write path `' . $path . '`');
        }

        return unlink($path);
    }

    protected function getEntityNamespace(): string
    {
        return $this->getNamespace();
    }
}
