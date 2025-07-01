<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo;

use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Optimization;
use MyBB\View\Themelet\ThemeletInterface;

use function MyBB\app;

/**
 * The base class for Repositories managing entities and related manifests in a Themelet's namespace.
 */
abstract class Repository extends \MyBB\Cargo\Repository
{
    public function __construct(
        public readonly ?ThemeletInterface $themelet,
        public readonly ?string $namespace,
    ) {
        $this->inheritanceManagedValueValidationType =
            app(Optimization::class)->getDirective('hierarchy.cacheValidationType');
    }

    public function getHierarchicalIdentifier(): string
    {
        return $this->themelet->getIdentifier();
    }

    /**
     * Returns a Repository with the same type and namespace
     * from the provided Themelet.
     */
    abstract public function getRepositoryInThemelet(ThemeletInterface $themelet): RepositoryInterface;

    protected function getPropertiesFilePath(): string
    {
        return
            $this->themelet->getNamespaceAbsolutePath($this->namespace) .
            '/' .
            static::NAME .
            '.json'
        ;
    }
}
