<?php

declare(strict_types=1);

namespace MyBB\Cargo;

use Illuminate\Support\Arr;

trait EntityTrait
{
    /**
     * Properties that can be discarded when the corresponding item is deleted.
     */
    private const FIRST_PARTY_PROPERTIES = [];

    public function getProperties(): array
    {
        $repository = $this->getRepository();

        return $repository->getEntityProperties(
            $this->getRepositoryEntityKey()
        );
    }

    public function setProperties(array $properties): void
    {
        $repository = $this->getRepository();

        $mergedProperties = array_merge_recursive(
            $this->getProperties(),
            $properties,
        );

        // normalize
        foreach ($mergedProperties as $key => $value) {
            if (
                $key === Repository::ANCESTOR_DECLARATIONS_KEY &&
                is_bool($value) &&
                !$repository->declaredInherited()
            ) {
                unset($mergedProperties[$key]);
            }
        }

        $repository->setEntityProperties(
            $this->getRepositoryEntityKey(),
            $mergedProperties,
        );
    }

    public function declaredInherited(): bool
    {
        return $this->getRepository()->entityDeclaredInherited(
            $this->getRepositoryEntityKey()
        );
    }

    public function deleteFirstPartyProperties(): void
    {
        $this->setProperties(
            Arr::except(
                $this->getProperties(),
                self::FIRST_PARTY_PROPERTIES,
            ),
        );
    }

    abstract public function getRepository(): RepositoryInterface;

    abstract protected function getRepositoryEntityKey(): string;
}
