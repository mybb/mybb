<?php

declare(strict_types=1);

namespace MyBB\Cargo\Decorator;

use BadMethodCallException;
use Illuminate\Support\Arr;
use LogicException;
use MyBB\Cargo\EntityInterface;
use MyBB\Cargo\FileRepositoryInterface;
use MyBB\Cargo\Repository;
use MyBB\Cargo\RepositoryInterface;
use MyBB\Utilities\ManagedValue\ManagedValue;

abstract class HierarchicalRepository extends RepositoryDecorator implements RepositoryInterface
{
    protected const NON_INHERITABLE_PROPERTIES = [
        Repository::ANCESTOR_DECLARATIONS_KEY,
    ];

    public ManagedValue $resolvedProperties;
    public ManagedValue $resolvedRepositories;

    public function __construct()
    {
        $this->resolvedProperties = (new ManagedValue())
            ->withDefault([
                Repository::SCOPE_SHARED => [],
                Repository::SCOPE_ENTITY => [],
            ]);
        $this->resolvedRepositories = (new ManagedValue())
            ->withDefault([]);
    }

    /**
     * @override decorated
     */
    public function getSharedProperties(): array
    {
        return $this->resolvedProperties->getNested([
            Repository::SCOPE_SHARED,
        ]);
    }

    /**
     * @override decorated
     */
    public function getSharedProperty(string $key): mixed
    {
        return $this->resolvedProperties->getNested([
            Repository::SCOPE_SHARED,
            $key,
        ]);
    }

    /**
     * @override decorated
     */
    public function setSharedProperty(string $key, mixed $value): void
    {
        $this->getOwnRepository()->setSharedProperty($key, $value);

        $this->resolvedProperties->build();
    }

    /**
     * @override decorated
     */
    public function getEntityProperties(?string $key = null): array
    {
        if ($key === null) {
            return $this->resolvedProperties->getNested([
                Repository::SCOPE_ENTITY,
            ]);
        } else {
            return $this->resolvedProperties->getNested([
                Repository::SCOPE_ENTITY,
                $key,
            ]) ?? [];
        }
    }

    /**
     * @override decorated
     */
    public function setEntityProperties(string $key, array $data): void
    {
        $this->getOwnRepository()->setEntityProperties($key, $data);

        $this->resolvedProperties->build();
    }

    /**
     * @override decorated
     */
    public function getAll(...$args): array
    {
        if (!($this->getDecorated() instanceof FileRepositoryInterface)) {
            throw new BadMethodCallException('`' . __FUNCTION__ . '()` can only be used on decorated Repositories implementing `' . FileRepositoryInterface::class . '`');
        }

        $results = [];
        $disinherited = [];

        foreach ($this->getRepositories() as $repository) {
            $entities = $repository->getAll(...$args);

            foreach ($entities as $key => $entity) {
                if (!in_array($key, $disinherited)) {
                    $results[$key] ??= $this->get($key); // use own decorated method
                }
            }

            $disinherited = array_merge(
                $disinherited,
                $repository->getEntitiesDeclaredDisinherited(),
            );
        }

        return $results;
    }

    public function getResolved(string $key): ?EntityInterface
    {
        return $this->getResolvedRepository($key)?->get($key);
    }

    /**
     * Returns the closest entity in the inheritance chain, excluding own Repository.
     */
    public function getClosestEntityAncestor(string $key): ?EntityInterface
    {
        if (!$this->getOwnRepository()->has($key)) {
            // avoid ambiguous usage when implied reference is missing
            throw new LogicException('`' . __FUNCTION__ . '`() cannot be called without existing reference entity');
        }

        return $this->getClosestEntityAncestorRepository($key)?->get($key);
    }

    /**
     * Whether the entity's effective Repository is an ancestor.
     */
    public function entityResolvesToAncestor(string $key): bool
    {
        $resolvedRepository = $this->getResolvedRepository($key);

        return (
            $resolvedRepository !== null &&
            $resolvedRepository !== $this->getOwnRepository()
        );
    }

    /**
     * Whether the reference entity has ancestors.
     */
    public function entityHasAncestors(string $key): bool
    {
        if (!$this->getOwnRepository()->has($key)) {
            // avoid ambiguous usage when implied reference is missing
            throw new LogicException('`' . __FUNCTION__ . '`() cannot be called without existing reference entity');
        }

        return $this->getClosestEntityAncestorRepository($key) !== null;
    }

    /**
     * Returns the entity's effective Repository using cache.
     */
    public function getResolvedRepository(string $key): ?RepositoryInterface
    {
        $repository = $this->resolvedRepositories->getNested([
            $key,
        ]);

        if ($repository !== null) {
            return $repository;
        }

        return $this->resolveRepository($key);
    }

    /**
     * Returns the entity's effective Repository, and caches the result.
     */
    public function resolveRepository(string $key): ?RepositoryInterface
    {
        $repository = $this->queryRepository($key);

        if ($repository === null) {
            $this->resolvedRepositories->deleteNested([
                $key,
            ]);
        } else {
            $this->resolvedRepositories->setNested([
                $key,
            ], $repository);
        }

        return $repository;
    }

    /**
     * Returns the entity's effective Repository.
     */
    public function queryRepository(string $key): ?RepositoryInterface
    {
        return $this->getOwnRepository()->has($key)
            ? $this->getOwnRepository()
            : $this->getClosestEntityAncestorRepository($key)
        ;
    }

    public function getClosestEntityAncestorRepository(string $key): ?RepositoryInterface
    {
        return $this->getEntityAncestorRepositories($key)?->current();
    }

    /**
     * @return iterable<RepositoryInterface>
     */
    public function getEntityAncestorRepositories(string $key): iterable
    {
        foreach ($this->getRepositories() as $repository) {
            if (
                $repository !== $this->getOwnRepository() &&
                $repository->has($key)
            ) {
                yield $repository;
            } elseif (
                !$repository->entityDeclaredInherited($key)
            ) {
                break;
            }
        }
    }

    public function getOwnRepository(): RepositoryInterface
    {
        return $this->getDecorated();
    }

    protected function buildResolvedProperties(&$stamp = []): array
    {
        $results = [
            Repository::SCOPE_SHARED => [],
            Repository::SCOPE_ENTITY => [],
        ];

        $disinherited = [];

        foreach ($this->getRepositories() as $repository) {
            $results[Repository::SCOPE_SHARED] = $this->getMergedProperties(
                $repository->getSharedProperties(),
                $results[Repository::SCOPE_SHARED],
            );

            foreach ($repository->getEntityProperties() as $identifier => $entityProperties) {
                if (!in_array($identifier, $disinherited)) {
                    $results[Repository::SCOPE_ENTITY][$identifier] = $this->getMergedProperties(
                        $entityProperties,
                        $results[Repository::SCOPE_SHARED][$identifier] ?? [],
                    );

                    if (!$repository->entityDeclaredInherited($identifier)) {
                        $disinherited[] = $identifier;
                    }
                }
            }

            $stamp[$repository->getHierarchicalIdentifier()] = $repository->getStamp();

            if (!$repository->declaredInherited()) {
                break;
            }
        }

        return $results;
    }

    protected function buildResolvedRepositories(&$stamp = []): array
    {
        $results = [];

        foreach ($this->getAll() as $key => $entity) {
            $repository = $this->queryRepository($key);

            $results[$key] = $repository;
        }

        $stamp = $this->getStamp();

        return $results;
    }

    protected function getMergedProperties(array $old, array $new): array
    {
        return $this->getDecorated()::getMergedProperties([
            Arr::except(
                $new,
                self::NON_INHERITABLE_PROPERTIES,
            ),
            $old,
        ]);
    }

    /**
     * Returns ancestor Repositories from closest to furthest.
     *
     * @return RepositoryInterface[]
     */
    protected function getAncestors(): array
    {
        return array_filter(
            $this->getRepositories(),
            fn ($repository) => $repository !== $this->getOwnRepository(),
        );
    }

    /**
     * Returns source Repositories in descending priority.
     *
     * @return RepositoryInterface[]
     */
    abstract protected function getRepositories(): array;
}
