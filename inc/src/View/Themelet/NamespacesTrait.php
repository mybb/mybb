<?php

declare(strict_types=1);

namespace MyBB\View\Themelet;

use FilesystemIterator;
use InvalidArgumentException;
use MyBB\View\NamespaceType;
use SplFileInfo;

trait NamespacesTrait
{
    /**
     * @var NamespaceType[]
     */
    private array $namespaceTypeAccess = [];

    /**
     * An implied and only namespace for Resources,
     * located directly in the Themelet directory.
     */
    private ?string $directNamespace = null;

    public function hasNamespaceTypeAccess(NamespaceType $type): bool
    {
        return in_array($type, $this->namespaceTypeAccess);
    }

    /**
     * @return string[]
     */
    public function getNamespaces(): array
    {
        if ($this->directNamespace) {
            return [
                $this->directNamespace,
            ];
        } else {
            $directoryNames = array_keys(
                array_filter(
                    iterator_to_array(
                        new FilesystemIterator(
                            $this->getAbsolutePath(),
                            FilesystemIterator::KEY_AS_FILENAME
                            | FilesystemIterator::CURRENT_AS_FILEINFO
                            | FilesystemIterator::SKIP_DOTS
                        )
                    ),
                    fn(SplFileInfo $item) => $item->isDir(),
                )
            );

            return array_map(
                $this->getNamespaceFromDirectoryName(...),
                $directoryNames,
            );
        }
    }

    /**
     * @return array<string, string[]>
     */
    public function getNamespaceAbsolutePaths(): array
    {
        $paths = [];

        foreach ($this->getNamespaces() as $namespace) {
            $paths[$namespace][] = $this->getNamespaceAbsolutePath($namespace);
        }

        return $paths;
    }

    public function getNamespaceAbsolutePath(string $namespace): string
    {
        $directoryPath = $this->getNamespaceDirectoryPath($namespace);

        if ($directoryPath === '.') {
            return $this->getAbsolutePath();
        } else {
            return $this->getAbsolutePath() . '/' . $directoryPath;
        }
    }

    public function getNamespaceDirectoryPath(string $namespace): string
    {
        $namespaceType = NamespaceType::tryFromNamespace($namespace, $this->getExtension());

        if ($namespaceType === null) {
            throw new InvalidArgumentException('Invalid namespace `' . $namespace . '`');
        }

        if ($namespace === $this->directNamespace) {
            return '.';
        } else {
            return $namespace;
        }
    }

    public function getNamespaceFromDirectoryName(string $name): string
    {
        if ($name === $this->directNamespace) {
            throw new InvalidArgumentException('Resources in Extension\'s own namespace `' . $name . '` must be placed directly in the view directory');
        }

        if ($this->directNamespace && $name === '.') {
            return $this->directNamespace;
        }

        $namespaceType = NamespaceType::tryFromNamespace($name, $this->extension);

        if ($namespaceType === null) {
            throw new InvalidArgumentException('Invalid namespace directory `' . $name . '`');
        }

        if (!$this->hasNamespaceTypeAccess($namespaceType)) {
            throw new InvalidArgumentException('Extension `' . $this->getExtension()->getPackageName() . '` cannot populate namespace `' . $name . '`');
        }

        return $name;
    }
}
