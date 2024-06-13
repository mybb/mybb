<?php

declare(strict_types=1);

namespace MyBB\View;

use Exception;
use LogicException;
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
    public function __construct(ThemeletInterface $themelet, ThemeletLocator $locator)
    {
        parent::__construct($themelet, $locator);

        if (!HierarchicalThemelet::decorates($themelet)) {
            throw new Exception('`' . __CLASS__ . '` must be associated with a Hierarchical Themelet');
        }
    }

    public function exists(): bool
    {
        return $this->getResolved()?->exists() === true;
    }

    public function setContent(string $content, $pointer = null, bool $normalize = false): bool
    {
        $ownRepository = $this->getRepository()->getOwnRepository();

        if ($ownRepository->has($this->getLocator())) {
            $ownResource = $ownRepository->getExisting($this->getLocator());
        } else {
            $ownResource = $ownRepository->create($this->getLocator());
        }

        $result = $ownResource->write($content, $pointer);

        if ($normalize && $this->contentMatchesInherited()) {
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

        if ($this->getInherited()) {
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
        $ownRepository = $this->getRepository()->getOwnRepository();

        $resource = $ownRepository->get($this->getLocator());

        if (!$resource->declaredInherited()) {
            $resource->setProperties([
                Repository::ANCESTOR_DECLARATIONS_KEY => true,
            ]);
        }

        if ($this->getInherited()) {
            $resource->delete();
        }

        $this->resolve();
    }

    public function getAbsolutePath(): string
    {
        return $this->getResolved()->getAbsolutePath();
    }

    public function getResolved(): ?Resource
    {
        return $this->getRepository()->getResolved($this->getLocator());
    }

    public function declaredInherited(): bool
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function deleteFirstPartyProperties(): void
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function getProperties(): array
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function setProperties(array $properties): void
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function contentMatchesInherited(): bool
    {
        $closestInheritedResource = $this->getInherited();

        if (!$closestInheritedResource) {
            return false;
        }

        return $this->getContent() === $closestInheritedResource->getContent();
    }

    /**
     * Returns the closest ancestor from which the Resource may be inherited.
     */
    public function getInherited(): ?Resource
    {
        $repository = $this->getRepository()->getClosestEntityAncestorRepository($this->locator);

        return $repository?->get($this->locator);
    }

    public function resolve(): void
    {
        $this->getRepository()->resolveRepository(
            $this->getLocator()
        );
    }
}
