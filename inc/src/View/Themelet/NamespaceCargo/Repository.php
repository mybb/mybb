<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo;

use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Themelet\ThemeletInterface;

/**
 * Metadata of items in a Themelet's namespace.
 */
abstract class Repository extends \MyBB\Cargo\Repository
{
    public function __construct(
        public readonly ?ThemeletInterface $themelet,
        public readonly ?string $namespace,
    ) {}

    public function getHierarchicalIdentifier(): string
    {
        return $this->themelet->getIdentifier();
    }

    /**
     * Returns a Repository with the same type and namespace
     * from the provided Themelet.
     */
    abstract public function getRepository(ThemeletInterface $themelet): RepositoryInterface;

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
