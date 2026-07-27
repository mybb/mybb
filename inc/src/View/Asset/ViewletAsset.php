<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use InvalidArgumentException;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\Decorator\PublishableViewlet;
use MyBB\View\Viewlet\ViewletInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use UnexpectedValueException;

/**
 * An Asset created from a Viewlet Resource.
 *
 * @property ViewletLocator $locator
 */
class ViewletAsset extends Asset
{
    public const WEB_ROOT_RELATIVE_BASE_PATH = 'cache/viewlets/';
    public const ABSOLUTE_BASE_PATH = MYBB_ROOT . self::WEB_ROOT_RELATIVE_BASE_PATH;

    private Resource $resource;

    public function __construct(
        readonly protected ViewletLocator $locator,
        readonly protected ViewletInterface $viewlet,
    )
    {
        if (!PublishableViewlet::decorates($viewlet)) {
            throw new InvalidArgumentException('PublishableViewlet required for `' . static::class . '`');
        }
    }

    public function getLocator(): ViewletLocator
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
     * Return an HTTP-accessible path to the file, relative to the MyBB root directory.
     */
    public function getPublicPath(): string
    {
        return
            $this->viewlet->getPublishingPath(
                $this->locator->getNamespace(),
                $this->locator->getType(),
            ) .
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

    public function getGroup(): ?string
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

            $this->resource = $this->getViewlet()->getResource($resourceLocator);
        }

        return $this->resource;
    }

    public function exists(): bool
    {
        return file_exists(
            $this->getAbsolutePath()
        );
    }

    /**
     * Returns the modification time as a Unix timestamp.
     */
    public function getModificationTime(): int
    {
        $path = $this->getAbsolutePath();

        $time = filemtime($path);

        if ($time === false) {
            throw new RuntimeException('Could not retrieve modification time for `' . $path . '`');
        }

        return $time;
    }

    public function write(string $content, $pointer = null): bool
    {
        $path = $this->getAbsolutePath();

        $this->validateWritePath($path);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        if ($pointer !== null) {
            $fh = $pointer;
        } else {
            $fh = fopen($path, 'cb');

            if ($fh === false) {
                throw new RuntimeException('Failed to open `' . $path . '`');
            }

            if (!flock($fh, LOCK_EX)) {
                fclose($fh);

                throw new RuntimeException('Failed to acquire exclusive lock for `' . $path . '`');
            }
        }

        $result =
            ftruncate($fh, 0) &&
            rewind($fh) &&
            fwrite($fh, $content) !== false;

        fflush($fh);

        if ($pointer === null) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $result;
    }

    public function delete(): bool
    {
        $path = $this->getAbsolutePath();

        $this->validateWritePath($path);

        return unlink($path);
    }

    protected function getEntityNamespace(): string
    {
        return $this->getNamespace();
    }

    protected function validateWritePath(string $path): void
    {
        if (
            !Path::isBasePath(self::ABSOLUTE_BASE_PATH, $path) ||
            Path::hasExtension($path, 'php', true)
        ) {
            throw new UnexpectedValueException('Illegal write path `' . $path . '`');
        }
    }
}
