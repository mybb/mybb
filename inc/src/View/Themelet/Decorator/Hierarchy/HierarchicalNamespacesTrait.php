<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\Decorator\Hierarchy;

use MyBB\View\NamespaceType;

trait HierarchicalNamespacesTrait
{
    /**
     * @override
     */
    public function hasNamespaceTypeAccess(NamespaceType $type): bool
    {
        throw self::decoratedCallException();
    }

    /**
     * @return string[]
     * @override
     */
    public function getNamespaces(): array
    {
        return array_keys(
            $this->getThemeletsByNamespace()
        );
    }

    /**
     * Returns absolute paths at which Resource namespaces may be found, in descending priority.
     *
     * @return array<string, string[]>
     * @override
     */
    public function getNamespaceAbsolutePaths(): array
    {
        $pathsByNamespace = [];

        foreach ($this->getThemeletsByNamespace() as $namespace => $themelets) {
            foreach ($themelets as $themelet) {
                $pathsByNamespace[$namespace][] = $themelet->getNamespaceAbsolutePath($namespace);
            }
        }

        return $pathsByNamespace;
    }

    /**
     * @override
     */
    public function getNamespaceAbsolutePath(string $namespace): string
    {
        throw self::decoratedCallException();
    }

    /**
     * @override
     */
    public function getNamespaceDirectoryPath(string $namespace): string
    {
        throw self::decoratedCallException();
    }

    /**
     * @override
     */
    public function getNamespaceFromDirectoryName(string $name): string
    {
        throw self::decoratedCallException();
    }
}
