<?php

declare(strict_types=1);

namespace MyBB\Extensions\Contracts;

use MyBB\Extensions\Repository;

/**
 * An Extension that may inherit from other Extensions of the same type.
 */
interface HierarchicalExtensionInterface
{
    public function getInheritanceChain(Repository $repository): array;
    public function getAncestors(Repository $repository): array;
}
