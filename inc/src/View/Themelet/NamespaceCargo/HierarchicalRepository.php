<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo;

use MyBB\Cargo\Repository;
use MyBB\Cargo\RepositoryInterface;
use MyBB\Stopwatch\Stopwatch;
use MyBB\Utilities\FileStamp;
use MyBB\Utilities\Hydrable\Hydrable;
use MyBB\View\Locator\ThemeletLocator;

use function MyBB\app;
use function MyBB\View\directive;

class HierarchicalRepository extends \MyBB\Cargo\Decorator\HierarchicalRepository
{
    public function __construct()
    {
        $cacheMode = directive('hierarchy.cache')
            ? Hydrable::MODE_DEFERRED
            : Hydrable::MODE_PASSIVE
        ;
        $validateMode = directive('hierarchy.cacheValidation')
            ? Hydrable::MODE_IMMEDIATE
            : Hydrable::MODE_PASSIVE
        ;

        $hydrables = $this->themelet->getHydrableRepository();

        $this->resolvedProperties = $hydrables->add(
            new Hydrable(
                [
                    Repository::SCOPE_SHARED => [],
                    Repository::SCOPE_ENTITY => [],
                ],
                key: 'hierarchy.properties.' . $this->getDecorated()::NAME,
                path: [$this->namespace],
                build: $this->buildResolvedProperties(...),
                validateStamp: $this->stampValid(...),
                writeMode: $cacheMode,
                readMode: $cacheMode,
                validateStampMode: $validateMode,
            ),
        );
        $this->resolvedRepositories = $hydrables->add(
            new Hydrable(
                [],
                key: 'hierarchy.resolution.' . $this->getDecorated()::NAME,
                path: [$this->namespace],
                build: $this->buildResolvedRepositories(...),
                validateStamp: $this->stampValid(...),
                write: fn (array $data) => array_map(
                    fn (Repository $repository) => $repository->getHierarchicalIdentifier(),
                    $data,
                ),
                read: fn (array $data) => array_map(
                    fn (string $identifier) => $this->getRepository(
                        $this->themelet->getThemelet($identifier)
                    ),
                    $data,
                ),
                buildMode: $cacheMode,
                writeMode: $cacheMode,
                readMode: $cacheMode,
                validateStampMode: $validateMode,
            ),
        );
    }

    public function getResolvedRepository(string|ThemeletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getResolvedRepository($key);
    }

    public function resolveRepository(string|ThemeletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        $repository = $this->queryRepository($key);

        if ($repository !== null) {
            $this->resolvedRepositories->setNested([
                $key,
            ], $repository);
        }

        return $repository;
    }

    public function queryRepository(string|ThemeletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ThemeletLocator) {
            $locator = $key;
            $key = $key->getNamespaceRelativeIdentifier();
        } else {
            $locator = ThemeletLocator::fromNamespaceRelativeIdentifier(
                $this->namespace,
                $key,
            );
        }

        $stopwatchPeriod = app(Stopwatch::class)->start(
            $locator->getString(),
            'core.view.hierarchy.resolution',
        );

        $repository = $this->getOwnRepository()->has($key)
            ? $this->getOwnRepository()
            : $this->getClosestEntityAncestorRepository($key)
        ;

        $stopwatchPeriod->stop();

        return $repository;
    }

    public function getClosestEntityAncestorRepository(string|ThemeletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return $this->getEntityAncestorRepositories($key)?->current();
    }

    /**
     * @return iterable<RepositoryInterface>
     */
    public function getEntityAncestorRepositories(string|ThemeletLocator $key): iterable
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        foreach ($this->getRepositories() as $repository) {
            if (
                $repository !== $this->getOwnRepository() &&
                $repository->has($key)
            ) {
                yield $repository;
            } elseif (!$repository->entityDeclaredInherited($key)) {
                break;
            }
        }
    }

    public function getOwnRepository(): RepositoryInterface
    {
        return $this->getRepository(
            $this->themelet->getOwnThemelet()
        );
    }

    /**
     * @param FileStamp::TYPE_* $type
     */
    public function stampValid(array $stamp, string $type = FileStamp::TYPE_MODIFICATION_TIME): bool
    {
        $repositories = $this->getRepositories();

        if (array_keys($repositories) !== array_keys($stamp)) {
            return false;
        }

        foreach ($repositories as $identifier => $repository) {
            $repositoryStamp = new FileStamp($stamp[$identifier]);

            if (!$repository->stampValid($repositoryStamp)) {
                return false;
            }
        }

        return true;
    }

    public function getStamp(): array
    {
        $stamps = [];

        foreach ($this->getRepositories() as $name => $repository) {
            $stamps[$name] = $repository->getStamp();
        }

        return $stamps;
    }

    public function buildCache(): void
    {
        $this->resolvedProperties->build();
        $this->resolvedRepositories->build();
    }

    protected function buildResolvedProperties(&$stamp = []): array
    {
        $stopwatchPeriod = app(Stopwatch::class)->start(
            '@' . $this->namespace . ' (' . $this->getDecorated()::NAME . ')',
            'core.view.hierarchy.properties',
        );

        $results = parent::buildResolvedProperties($stamp);

        $stopwatchPeriod->stop();

        return $results;
    }

    protected function buildResolvedRepositories(&$stamp = []): array
    {
        $stopwatchPeriod = app(Stopwatch::class)->start(
            '@' . $this->namespace . ' (' . $this->getDecorated()::NAME . ')',
            'core.view.hierarchy.repositories',
        );

        $results = parent::buildResolvedRepositories($stamp);

        $stopwatchPeriod->stop();

        return $results;
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
    protected function getRepositories(): array
    {
        $results = [];

        $themeletsByPriority = array_reverse(
            $this->themelet->getThemeletsByNamespace($this->namespace)
        );

        foreach ($themeletsByPriority as $themelet) {
            $results[$themelet->getIdentifier()] = $this->getRepository($themelet);
        }

        return $results;
    }
}
