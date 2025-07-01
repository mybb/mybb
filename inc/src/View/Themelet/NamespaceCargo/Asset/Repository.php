<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo\Asset;

use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Themelet\ThemeletInterface;

/**
 * Metadata of items in a Themelet's namespace.
 */
class Repository extends \MyBB\View\Themelet\NamespaceCargo\Repository
{
    public const NAME = 'assets';

    public function getRepositoryInThemelet(ThemeletInterface $themelet): RepositoryInterface
    {
        return $themelet->getAssetRepository($this->namespace);
    }
}
