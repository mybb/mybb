<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo\Resource\Decorator;

use LogicException;
use MyBB\Cargo\EntityInterface as CargoEntityInterface;
use MyBB\Cargo\FileRepositoryInterface;
use MyBB\View\HierarchicalResource;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;

/**
 * An inheritance-aware Resource Repository.
 */
class HierarchicalRepository extends \MyBB\View\Themelet\NamespaceCargo\HierarchicalRepository implements FileRepositoryInterface
{
    /**
     * @override decorated scope
     */
    public function has(string|ThemeletLocator $key): bool
    {
        return $this->get($key)->exists();
    }

    /**
     * @override decorated scope
     */
    public function getExisting(string|ThemeletLocator $key): ?Resource
    {
        $resource = $this->get($key);

        if ($resource->exists()) {
            return $resource;
        } else {
            return null;
        }
    }

    /**
     * @override decorated scope
     */
    public function get(string|ThemeletLocator $key): Resource
    {
        if (!($key instanceof ThemeletLocator)) {
            $key = ThemeletLocator::fromNamespaceRelativeIdentifier($this->namespace, $key);
        }

        return new HierarchicalResource($this->themelet, $key);
    }

    /**
     * @override decorated
     */
    public function create(string|ThemeletLocator $key): Resource
    {
        $resource = $this->getOwnRepository()->get($key);

        if ($resource->exists()) {
            throw new LogicException('Resource `' . $key->getString() . '` already exists');
        } else {
            return $resource;
        }
    }

    public function getResolved(string|ThemeletLocator $key): ?CargoEntityInterface
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getResolved($key);
    }
}
