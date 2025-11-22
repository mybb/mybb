<?php

declare(strict_types=1);

namespace MyBB\View;

use Exception;
use LogicException;
use MyBB\Cargo\HierarchicalEntityTrait;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Viewlet\Decorator\Hierarchy\HierarchicalViewlet;
use MyBB\View\Viewlet\NamespaceCargo\Repository;
use MyBB\View\Viewlet\ViewletInterface;

/**
 * Inheritance-aware Resource.
 *
 * Reads from resolved Viewlets; writes to own Viewlet. Properties not inherited.
 */
readonly class HierarchicalResource extends Resource
{
    use HierarchicalEntityTrait;

    public function __construct(ViewletInterface $viewlet, ViewletLocator $locator)
    {
        parent::__construct($viewlet, $locator);

        if (!HierarchicalViewlet::decorates($viewlet)) {
            throw new Exception('`' . __CLASS__ . '` must be associated with a Hierarchical Viewlet');
        }
    }

    public function getProperties(): array
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function exists(): bool
    {
        return $this->getResolved()?->exists() === true;
    }

    public function setContent(string $content, $pointer = null, bool $normalize = false): bool
    {
        $ownResource = $this->getOwn();

        $result = $ownResource->setContent($content, $pointer);

        if ($normalize && $this->hasAncestors() && $this->contentMatchesInherited()) {
            $ownResource->delete();
        }

        $this->resolve();

        return $result;
    }

    public function delete(): void
    {
        $ownRepository = $this->getRepository()->getOwnRepository();

        $resource = $ownRepository->getExisting($this->getLocator());

        $resource->delete();

        if ($this->hasAncestors()) {
            $resource->setProperties([
                Repository::ANCESTOR_DECLARATIONS_KEY => false,
            ]);
        }

        $this->resolve();
    }

    /**
     * Makes a Viewlet's Resource inherit its content.
     */
    public function revert(): void
    {
        $resource = $this->getOwn();

        if (!$resource->declaredInherited()) {
            $resource->setProperties([
                Repository::ANCESTOR_DECLARATIONS_KEY => true,
            ]);
        }

        if ($this->hasAncestors()) {
            $resource->delete();
        }

        $this->resolve();
    }

    public function getAbsolutePath(): string
    {
        return $this->getResolved()->getAbsolutePath();
    }

    public function contentMatchesInherited(): bool
    {
        $closestInheritedResource = $this->getClosestAncestor();

        if (!$closestInheritedResource) {
            return false;
        }

        return $this->getContent() === $closestInheritedResource->getContent();
    }
}
