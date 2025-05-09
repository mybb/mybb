<?php

declare(strict_types=1);

namespace MyBB\View;

use Exception;
use MyBB\Cargo\EntityInterface as CargoEntityInterface;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Themelet\NamespaceCargo\EntityTrait;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Themelet\ThemeletInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Path;

readonly class Resource implements CargoEntityInterface
{
    use EntityTrait;

    protected ThemeletInterface $themelet;

    protected ThemeletLocator $locator;

    public function __construct(ThemeletInterface $themelet, ThemeletLocator $locator)
    {
        $this->themelet = $themelet;
        $this->locator = $locator;
    }

    public function exists(): bool
    {
        return file_exists(
            $this->getAbsolutePath()
        );
    }

    public function getModificationTime(): ?int
    {
        return filemtime(
            $this->getAbsolutePath()
        );
    }

    public function getContent(): string
    {
        $path = $this->getAbsolutePath();

        return file_get_contents($path);
    }

    public function setContent(string $content, $pointer = null): bool
    {
        $path = realpath($this->getAbsolutePath());

        if (
            !Path::isBasePath(
                $this->getThemelet()->getExtension()::EXTENSION_TYPE_ABSOLUTE_BASE_PATH,
                $path
            ) ||
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

    public function delete(): void
    {
        $path = realpath($this->getAbsolutePath());

        if (
            !Path::isBasePath(
                $this->getThemelet()->getExtension()::EXTENSION_TYPE_ABSOLUTE_BASE_PATH,
                $path,
            ) ||
            Path::hasExtension($path, 'php')
        ) {
            throw new Exception('Illegal write path `' . $path . '`');
        }

        if (!unlink($path)) {
            throw new Exception('Could not delete file `' . $path . '`');
        }

        $this->deleteFirstPartyProperties();
    }

    public function getAbsolutePath(): string
    {
        return
            $this->getThemelet()->getResourceTypeAbsolutePath($this->getNamespace(), $this->getType()) .
            '/' .
            $this->getSubPath()
        ;
    }

    public function getIdentifierPath(): string
    {
        return
            $this->getNamespace() .
            '/' .
            $this->getType()->getDirectoryName() .
            '/' .
            $this->getSubPath()
        ;
    }

    public function getThemelet(): ThemeletInterface
    {
        return $this->themelet;
    }

    public function getLocator(): ThemeletLocator
    {
        return $this->locator;
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

    public function getLanguage(): ?ResourceLanguage
    {
        return ResourceLanguage::tryFromFilename(
            $this->getFilename()
        );
    }

    public function getRepository(): RepositoryInterface
    {
        return $this->getThemelet()->getResourceRepository(
            $this->getNamespace()
        );
    }
}
