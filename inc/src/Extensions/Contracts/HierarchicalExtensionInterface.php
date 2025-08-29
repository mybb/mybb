<?php

declare(strict_types=1);

namespace MyBB\Extensions\Contracts;

use MyBB\Extensions\Repository;

interface HierarchicalExtensionInterface
{
    public function getInheritanceChain(Repository $repository): array;
    public function getAncestors(Repository $repository): array;
}
