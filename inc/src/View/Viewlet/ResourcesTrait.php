<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet;

use InvalidArgumentException;
use MyBB\View\Locator\Exception as LocatorException;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\NamespaceCargo\Repository as NamespaceCargoRepository;
use MyBB\View\Viewlet\NamespaceCargo\Resource\Repository as ResourceRepository;
use Symfony\Component\Filesystem\Path;

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

    public function hasResource(ViewletLocator $locator): bool
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->has($locator);
    }

    public function getExistingResource(ViewletLocator $locator): ?Resource
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->getExisting($locator);
    }

    public function getResource(ViewletLocator $locator): Resource
    {
        $repository = $this->getResourceRepository($locator->getNamespace());

        return $repository->get($locator);
    }

    public function createResource(ViewletLocator $locator): ?Resource
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

    /**
     * Returns a Resource that may be found at the given absolute path.
     */
    public function getResourceFromAbsolutePath(string $path): Resource
    {
        if (!Path::isBasePath($this->getAbsolutePath(), $path)) {
            throw new InvalidArgumentException('Viewlet path mismatch for path `' . $path . '`');
        }

        $viewletRelativePath = Path::makeRelative($path, $this->getAbsolutePath());

        $locator = $this->getLocatorFromRelativePath($viewletRelativePath);

        return $this->getResource($locator);
    }

    /**
     * Returns the Locator of a Resource that may be found at the given Viewlet-relative path.
     */
    private function getLocatorFromRelativePath(string $path): ViewletLocator
    {
        if (
            $this->extension !== null &&
            $this->extension::VIEWLET_DIRECT_NAMESPACE === true
        ) {
            $namespace = $this->extension->getViewletDirectNamespace();
        } else {
            $namespace = explode('/', Path::canonicalize($path))[0] ?? null;

            if (
                $namespace === null ||
                !$this->hasNamespaceTypeAccess(NamespaceType::tryFromNamespace($namespace, $this->extension))
            ) {
                throw new InvalidArgumentException('Could not determine Resource namespace from path `' . $path . '`');
            }
        }

        try {
            return ViewletLocator::fromNamespaceRelativeIdentifier(
                $namespace,
                Path::makeRelative($path, $namespace)
            );
        } catch (LocatorException $e) {
            throw new InvalidArgumentException('Invalid Resource path', previous: $e);
        }
    }
}
