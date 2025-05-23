<?php

declare(strict_types=1);

namespace MyBB\Cargo;

use LogicException;

trait HierarchicalEntityTrait
{
    public function setProperties(array $properties): void
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function declaredInherited(): bool
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    public function deleteFirstPartyProperties(): void
    {
        throw new LogicException('`' . __FUNCTION__ . '()` cannot be called on `' . __CLASS__ . '`');
    }

    /**
     * Returns the entity from the decorated Repository, skipping inheritance logic.
     *
     * @return parent An instance of the base entity class.
     */
    public function getOwn(): parent
    {
        return $this->getRepository()->getOwnRepository()->get(
            $this->getRepositoryEntityKey()
        );
    }

    /**
     * Returns the appropriate entity according to inheritance logic.
     *
     * @return ?parent An instance of the base entity class.
     */
    public function getResolved(): ?parent
    {
        return $this->getRepository()->getResolved(
            $this->getRepositoryEntityKey()
        );
    }

    /**
     * Whether the entity's effective Repository is an ancestor.
     */
    public function resolvesToAncestor(): bool
    {
        return $this->getRepository()->entityResolvesToAncestor(
            $this->getRepositoryEntityKey()
        );
    }

    /**
     * Whether the decorated Repository's entity has ancestors.
     */
    public function hasAncestors(): bool
    {
        return $this->getRepository()->entityHasAncestors(
            $this->getRepositoryEntityKey()
        );
    }

    /**
     * Returns the closest entity in the inheritance chain, excluding own Repository.
     *
     * @return ?parent An instance of the base entity class.
     */
    public function getClosestAncestor(): ?parent
    {
        return $this->getRepository()->getClosestEntityAncestor(
            $this->getRepositoryEntityKey()
        );
    }

    /**
     * Builds the resolution cache.
     */
    public function resolve(): void
    {
        $this->getRepository()->resolveRepository(
            $this->getRepositoryEntityKey()
        );
    }
}
