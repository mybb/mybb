<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\Decorator\Hierarchy;

use MyBB\Cargo\Decorator\RepositoryDecorator;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\NamespaceCargo\Repository as NamespaceCargoRepository;
use MyBB\View\Viewlet\NamespaceCargo\Resource\Repository as ResourceRepository;
use MyBB\View\Viewlet\NamespaceCargo\Resource\Decorator\HierarchicalRepository as HierarchicalResourceRepository;
use MyBB\View\Viewlet\ResourcesTrait;

trait HierarchicalResourcesTrait
{
    use ResourcesTrait; // override decorated scope for remaining methods

    /**
     * Resource Repositories by namespace.
     *
     * @var array<string, NamespaceCargoRepository>
     */
    private array $resourceRepositories = [];

    public function getResourceRepository(string $namespace): RepositoryInterface
    {
        return $this->resourceRepositories[$namespace] ??=
            RepositoryDecorator::decorate(
                new ResourceRepository($this, $namespace),
                [
                    HierarchicalResourceRepository::class,
                ],
            )
        ;
    }

    /**
     * @override decorated
     */
    public function getResourceTypeAbsolutePath(string $namespace, ResourceType $type): string
    {
        throw self::decoratedCallException();
    }
}
