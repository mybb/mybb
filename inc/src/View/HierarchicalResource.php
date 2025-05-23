<?php

declare(strict_types=1);

namespace MyBB\View;

use Exception;
use LogicException;
use MyBB\Cargo\HierarchicalEntityTrait;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Themelet\Decorator\Hierarchy\HierarchicalThemelet;
use MyBB\View\Themelet\NamespaceCargo\Repository;
use MyBB\View\Themelet\ThemeletInterface;

/**
 * Inheritance-aware Resource.
 *
 * Reads from resolved Themelets; writes to own Themelet. Properties not inherited.
 */
readonly class HierarchicalResource extends Resource
{
    use HierarchicalEntityTrait;

    public function __construct(ThemeletInterface $themelet, ThemeletLocator $locator)
    {
        parent::__construct($themelet, $locator);

        if (!HierarchicalThemelet::decorates($themelet)) {
            throw new Exception('`' . __CLASS__ . '` must be associated with a Hierarchical Themelet');
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
     * Makes a Themelet's Resource inherit its content.
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
