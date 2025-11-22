<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

/**
 * Provides Asset data merged from selected namespaces.
 */
trait NamespacesTrait
{
    private ?string $mainNamespace = null;

    public function setMainNamespace(string $name): void
    {
        $this->mainNamespace = $name;

        $this->viewlet->applyNamespace($name);
    }

    public function getMainNamespace(): ?string
    {
        return $this->mainNamespace;
    }

    public function applyNamespace(string $name): void
    {
        $this->viewlet->applyNamespace($name);
    }
}
