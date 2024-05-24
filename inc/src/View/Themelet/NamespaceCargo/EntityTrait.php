<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo;

trait EntityTrait
{
    use \MyBB\Cargo\EntityTrait;

    public function getRepositoryEntityKey(): string
    {
        return $this->getLocator()->getNamespaceRelativeIdentifier();
    }
}
