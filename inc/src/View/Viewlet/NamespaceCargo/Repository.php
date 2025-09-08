<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\NamespaceCargo;

use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Optimization;
use MyBB\View\Viewlet\ViewletInterface;

use function MyBB\app;

/**
 * The base class for Repositories managing entities and related manifests in a Viewlet's namespace.
 */
abstract class Repository extends \MyBB\Cargo\Repository
{
    public function __construct(
        public readonly ?ViewletInterface $viewlet,
        public readonly ?string $namespace,
    ) {
        $this->inheritanceManagedValueValidationType =
            app(Optimization::class)->getDirective('hierarchy.cacheValidationType');
    }

    public function getHierarchicalIdentifier(): string
    {
        return $this->viewlet->getIdentifier();
    }

    /**
     * Returns a Repository with the same type and namespace
     * from the provided Viewlet.
     */
    abstract public function getRepositoryInViewlet(ViewletInterface $viewlet): RepositoryInterface;

    protected function getPropertiesFilePath(): string
    {
        return
            $this->viewlet->getNamespaceAbsolutePath($this->namespace) .
            '/' .
            static::NAME .
            '.json'
        ;
    }
}
