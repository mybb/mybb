<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\NamespaceCargo;

use MyBB\Cargo\Repository;
use MyBB\Cargo\RepositoryInterface;
use MyBB\Stopwatch\Stopwatch;
use MyBB\Utilities\FileStamp;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Optimization;

use function MyBB\app;

/**
 * The base class for inheritance-aware Namespace Cargo Repositories.
 */
class HierarchicalRepository extends \MyBB\Cargo\Decorator\HierarchicalRepository
{
    public function __construct(
        Optimization $optimization,
    )
    {
        $cacheMode = $optimization->getDirective('hierarchy.cache')
            ? ManagedValue::MODE_DEFERRED
            : ManagedValue::MODE_PASSIVE
        ;
        $validateMode = $optimization->getDirective('hierarchy.cacheValidation')
            ? ManagedValue::MODE_IMMEDIATE
            : ManagedValue::MODE_PASSIVE
        ;

        $managedValueRepository = $this->themelet->getManagedValueRepository();

        $this->resolvedProperties = $managedValueRepository->create([
            'hierarchy.properties.' . $this->getDecorated()::NAME,
            $this->namespace,
        ])
            ->withDefault([
                Repository::SCOPE_SHARED => [],
                Repository::SCOPE_ENTITY => [],
            ])
            ->withBuild(
                $this->buildResolvedProperties(...),
            )
            ->withSave(mode: $cacheMode)
            ->withLoad(mode: $cacheMode)
            ->withStampValidation(
                $this->stampValid(...),
                mode: $validateMode,
            );

        $this->resolvedRepositories = $managedValueRepository->create([
            'hierarchy.resolution.' . $this->getDecorated()::NAME,
            $this->namespace,
        ])
            ->withDefault([])
            ->withBuild(
                $this->buildResolvedRepositories(...),
                $cacheMode,
            )
            ->withSave(
                fn (array $data) => array_map(
                    fn (Repository $repository) => $repository->getHierarchicalIdentifier(),
                    $data,
                ),
                $cacheMode,
            )
            ->withLoad(
                fn (array $data) => array_map(
                    fn (string $identifier) => $this->getRepositoryInThemelet(
                        $this->themelet->getThemelet($identifier)
                    ),
                    $data,
                ),
                $cacheMode,
            )
            ->withStampValidation(
                $this->stampValid(...),
                $validateMode,
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

        return parent::resolveRepository($key);
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

        try {
            return parent::queryRepository($key);
        } finally {
            $stopwatchPeriod->stop();
        }
    }

    public function getClosestEntityAncestorRepository(string|ThemeletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getClosestEntityAncestorRepository($key);
    }

    /**
     * @return iterable<RepositoryInterface>
     */
    public function getEntityAncestorRepositories(string|ThemeletLocator $key): iterable
    {
        if ($key instanceof ThemeletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getEntityAncestorRepositories($key);
    }

    /**
     * Returns the concrete, non-inheritance-aware Repository associated with the concrete Themelet.
     *
     * @override decorated
     */
    public function getOwnRepository(): RepositoryInterface
    {
        return $this->getRepositoryInThemelet(
            $this->themelet->getOwnThemelet()
        );
    }

    /**
     * @param FileStamp::TYPE_* $type
     *
     * @override decorated
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

    /**
     * @override decorated
     */
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

    /**
     * Returns source Repositories in descending priority.
     *
     * @return RepositoryInterface[]
     */
    protected function getRepositories(): array
    {
        $results = [];

        $themelets = $this->themelet->getThemeletsByNamespace($this->namespace);

        foreach ($themelets as $themelet) {
            $results[$themelet->getIdentifier()] = $this->getRepositoryInThemelet($themelet);
        }

        return $results;
    }
}
