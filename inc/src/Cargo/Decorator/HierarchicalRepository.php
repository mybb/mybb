<?php

declare(strict_types=1);

namespace MyBB\Cargo\Decorator;

use BadMethodCallException;
use Illuminate\Support\Arr;
use LogicException;
use MyBB\Cargo\EntityInterface;
use MyBB\Cargo\StoreRepositoryInterface;
use MyBB\Cargo\Repository;
use MyBB\Cargo\RepositoryInterface;
use MyBB\Utilities\ManagedValue\ManagedValue;

/**
 * An inheritance-aware Cargo Repository.
 *
 * Provides resolution of entities to Repositories, merging of properties,
 *   and hierarchy information according to inheritance declarations.
 */
abstract class HierarchicalRepository extends RepositoryDecorator implements RepositoryInterface
{
    /**
     * Names of properties to exclude from the resolved list.
     */
    protected const NON_INHERITABLE_PROPERTIES = [
        Repository::ANCESTOR_DECLARATIONS_KEY,
    ];

    /**
     * Properties combined according to inheritance logic.
     *
     * @var ManagedValue<array{
     *   shared: array,
     *   entity: array<string, array>,
     * }>
     */
    public ManagedValue $resolvedProperties;

    /**
     * A mapping of entity keys to their effective Repositories.
     *
     * @var ManagedValue<array<string, RepositoryInterface>>
     */
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
        if (!($this->getDecorated() instanceof StoreRepositoryInterface)) {
            throw new BadMethodCallException('`' . __FUNCTION__ . '()` can only be used on decorated Repositories implementing `' . StoreRepositoryInterface::class . '`');
        }

        $results = [];
        $disinherited = [];

        foreach ($this->getRepositories() as $repository) {
            $entities = $repository->getAll(...$args);

            foreach ($entities as $key => $entity) {
                if (!in_array($key, $disinherited)) {
                    $results[$key] ??= $this->get($key);
                }
            }

            $disinherited = array_merge(
                $disinherited,
                $repository->getEntitiesDeclaredDisinherited(),
            );
        }

        return $results;
    }

    /**
     * Returns the effective entity.
     */
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

    /**
     * Returns the closest Repository in the inheritance chain, excluding own Repository, that contains the entity.
     */
    public function getClosestEntityAncestorRepository(string $key): ?RepositoryInterface
    {
        return $this->getEntityAncestorRepositories($key)?->current();
    }

    /**
     * Returns Repositories in the inheritance chain, excluding own Repository, that contain the entity.
     *
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

    /**
     * Returns the concrete, non-inheritance-aware Repository.
     */
    public function getOwnRepository(): RepositoryInterface
    {
        return $this->getDecorated();
    }

    /**
     * Returns shared and entity properties combined according to inheritance logic.
     *
     * @param-out array $stamp A stamp used for cache validation.
     */
    protected function buildResolvedProperties(&$stamp = []): array
    {
        $results = [
            Repository::SCOPE_SHARED => [],
            Repository::SCOPE_ENTITY => [],
        ];

        $disinheritedEntities = [];

        // iterate with descending priority
        foreach ($this->getRepositories() as $repository) {
            $results[Repository::SCOPE_SHARED] = $this->getMergedProperties(
                $repository->getSharedProperties(),
                $results[Repository::SCOPE_SHARED],
            );

            foreach ($repository->getEntityProperties() as $key => $entityProperties) {
                if (!in_array($key, $disinheritedEntities)) {
                    $results[Repository::SCOPE_ENTITY][$key] = $this->getMergedProperties(
                        $entityProperties,
                        $results[Repository::SCOPE_ENTITY][$key] ?? [],
                    );

                    if (!$repository->entityDeclaredInherited($key)) {
                        $disinheritedEntities[] = $key;
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

    /**
     * Returns a mapping of entity keys to their effective Repositories.
     *
     * @param-out array $stamp A stamp used for cache validation.
     *
     * @return array<string, RepositoryInterface>
     */
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

    /**
     * Returns the resulting set of properties assigned the same entity key.
     */
    protected function getMergedProperties(array $old, array $new): array
    {
        return $this->getDecorated()::getMergedProperties([
            Arr::except($old, self::NON_INHERITABLE_PROPERTIES),
            Arr::except($new, self::NON_INHERITABLE_PROPERTIES),
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
