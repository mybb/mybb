<?php

declare(strict_types=1);

namespace MyBB\Extensions\Contracts;

interface HierarchicalExtensionInterface
{
    public function getInheritanceChain(): array;
    public function getAncestors(): array;
}
