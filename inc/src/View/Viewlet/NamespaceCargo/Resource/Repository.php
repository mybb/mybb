<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\NamespaceCargo\Resource;

use FilesystemIterator;
use LogicException;
use MyBB\Cargo\StoreRepositoryInterface;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\ViewletInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Path;

/**
 * Manages Resources and their declarations in a Viewlet's namespace.
 */
class Repository extends \MyBB\View\Viewlet\NamespaceCargo\Repository implements StoreRepositoryInterface
{
    public const NAME = 'resources';

    public function getRepositoryInViewlet(ViewletInterface $viewlet): RepositoryInterface
    {
        return $viewlet->getResourceRepository($this->namespace);
    }

    /**
     * @param ?ResourceType[] $resourceTypes
     * @return array<string, Resource>
     */
    public function getAll(?array $resourceTypes = null): array
    {
        $results = [];

        foreach ($resourceTypes ?? ResourceType::cases() as $resourceType) {
            $resourceTypeAbsolutePath = $this->viewlet->getResourceTypeAbsolutePath(
                $this->namespace,
                $resourceType,
            );

            if (is_dir($resourceTypeAbsolutePath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $resourceTypeAbsolutePath,
                        FilesystemIterator::SKIP_DOTS,
                    )
                );

                foreach ($files as $file) {
                    $path = Path::makeRelative($file->getRealpath(), $resourceTypeAbsolutePath);

                    $locator = ViewletLocator::fromString(
                        $path,
                        [
                            'namespace' => ViewletLocator::COMPONENT_UNSET,
                            'type' => ViewletLocator::COMPONENT_UNSET,
                        ],
                        [
                            'namespace' => $this->namespace,
                            'type' => $resourceType,
                        ],
                    );

                    $key = $locator->getNamespaceRelativeIdentifier();

                    $results[$key] = $this->getExisting($locator);
                }
            }
        }

        return $results;
    }

    public function has(string|ViewletLocator $key): bool
    {
        return $this->get($key)->exists();
    }

    public function getExisting(string|ViewletLocator $key): ?Resource
    {
        $resource = $this->get($key);

        if ($resource->exists()) {
            return $resource;
        } else {
            return null;
        }
    }

    public function get(string|ViewletLocator $key): Resource
    {
        if (!($key instanceof ViewletLocator)) {
            $key = ViewletLocator::fromNamespaceRelativeIdentifier($this->namespace, $key);
        }

        return new Resource($this->viewlet, $key);
    }

    public function create(string|ViewletLocator $key): Resource
    {
        $resource = $this->get($key);

        if ($resource->exists()) {
            throw new LogicException('Resource `' . $key->getString() . '` already exists');
        } else {
            return $resource;
        }
    }
}
