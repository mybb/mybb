<?php

declare(strict_types=1);

namespace MyBB\View\Themelet;

use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\NamespaceCargo\Repository as NamespaceCargoRepository;
use MyBB\View\Themelet\NamespaceCargo\Resource\Repository as ResourceRepository;

trait ResourcesTrait
{
    /**
     * Resource Repositories by namespace.
     *
     * @var array<string, NamespaceCargoRepository>
     */
    private array $resourceRepositories = [];

    public function getResourceRepository(string $namespace): NamespaceCargoRepository
    {
        return $this->resourceRepositories[$namespace] ??=
            new ResourceRepository($this, $namespace);
    }

    public function getResourceTypeAbsolutePath(string $namespace, ResourceType $type): string
    {
        return
            $this->getNamespaceAbsolutePath($namespace) .
            '/' .
            $type->getDirectoryName()
        ;
    }

    /**
     * Returns absolute paths at which Resources of specified type may be found,
     * by namespace, in descending priority.
     *
     * @return array<string, string[]>
     */
    public function getResourceTypeAbsolutePaths(ResourceType $type): array
    {
        $resultPaths = [];

        $namespacePaths = $this->getNamespaceAbsolutePaths();

        foreach ($namespacePaths as $namespace => $paths) {
            foreach ($paths as $path) {
                $resultPaths[$namespace][] = $path . '/' . $type->getDirectoryName();
            }
        }

        return $resultPaths;
    }

    /**
     * @param ?string[] $namespaces
     * @param ?ResourceType[] $resourceTypes
     * @return array<string, Resource>
     */
    public function getResources(?array $namespaces = null, ?array $resourceTypes = null): array
    {
        $resources = [];

        foreach ($namespaces ?? $this->getNamespaces() as $namespace) {
            foreach ($this->getNamespaceResources($namespace, $resourceTypes) as $resource) {
                // use Locator strings with namespaces
                $resources[$resource->getLocator()->getString()] = $resource;
            }
        }

        return $resources;
    }

    /**
     * @param ?ResourceType[] $resourceTypes
     * @return array<string, Resource>
     */
    public function getNamespaceResources(string $namespace, ?array $resourceTypes = null): array
    {
        $repository = $this->getResourceRepository($namespace);

        return $repository->getAll($resourceTypes);
    }

    public function hasResource(ThemeletLocator $locator): bool
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->has($locator);
    }

    public function getExistingResource(ThemeletLocator $locator): ?Resource
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->getExisting($locator);
    }

    public function getResource(ThemeletLocator $locator): Resource
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->get($locator);
    }

    public function createResource(ThemeletLocator $locator): ?Resource
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->create($locator);
    }

    public function getResourceProperties(): array
    {
        $results = [];

        foreach ($this->getNamespaces() as $namespace) {
            $repository = $this->getResourceRepository($namespace);

            $results = array_merge($results, $repository->getEntityProperties());
        }

        return $results;
    }
}
