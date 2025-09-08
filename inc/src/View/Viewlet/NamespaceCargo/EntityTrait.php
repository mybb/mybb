<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\NamespaceCargo;

trait EntityTrait
{
    use \MyBB\Cargo\EntityTrait;

    public function getRepositoryEntityKey(): string
    {
        return $this->getLocator()->getNamespaceRelativeIdentifier();
    }
}
