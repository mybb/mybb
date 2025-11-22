<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\NamespaceCargo\Asset;

use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Viewlet\ViewletInterface;

/**
 * Manages Asset declarations in a Viewlet's namespace.
 */
class Repository extends \MyBB\View\Viewlet\NamespaceCargo\Repository
{
    public const NAME = 'assets';

    public function getRepositoryInViewlet(ViewletInterface $viewlet): RepositoryInterface
    {
        return $viewlet->getAssetRepository($this->namespace);
    }
}
