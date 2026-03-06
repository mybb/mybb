<?php

declare(strict_types=1);

namespace MyBB\View;

use Exception;
use MyBB\Cargo\EntityInterface as CargoEntityInterface;
use MyBB\Cargo\EntityTrait;
use MyBB\Cargo\RepositoryInterface;
use MyBB\Utilities\CodeLanguage;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Viewlet\ViewletInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use UnexpectedValueException;

readonly class Resource implements CargoEntityInterface
{
    use EntityTrait;

    protected ViewletInterface $viewlet;

    protected ViewletLocator $locator;

    public function __construct(ViewletInterface $viewlet, ViewletLocator $locator)
    {
        $this->viewlet = $viewlet;
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
        $path = realpath(
            $this->getAbsolutePath()
        );

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

    public function delete(): void
    {
        $path = realpath(
            $this->getAbsolutePath()
        );

        $this->validateWritePath($path);

        if (!unlink($path)) {
            throw new Exception('Could not delete file `' . $path . '`');
        }

        $this->deleteFirstPartyProperties();
    }

    public function getAbsolutePath(): string
    {
        return
            $this->getViewlet()->getResourceTypeAbsolutePath($this->getNamespace(), $this->getType()) .
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

    public function getViewlet(): ViewletInterface
    {
        return $this->viewlet;
    }

    public function getLocator(): ViewletLocator
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

    public function getCodeLanguage(): ?CodeLanguage
    {
        return CodeLanguage::tryFromFilename(
            $this->getFilename()
        );
    }

    public function getRepository(): RepositoryInterface
    {
        return $this->getViewlet()->getResourceRepository(
            $this->getNamespace()
        );
    }

    public function getRepositoryKey(): string
    {
        return $this->getLocator()->getNamespaceRelativeIdentifier();
    }

    protected function validateWritePath(string $path): void
    {
        if (
            !Path::isBasePath(
                $this->getViewlet()->getExtension()::EXTENSION_TYPE_ABSOLUTE_BASE_PATH,
                $path,
            ) ||
            Path::hasExtension($path, 'php', true)
        ) {
            throw new UnexpectedValueException('Illegal write path `' . $path . '`');
        }
    }
}
