<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\NamespaceCargo;

use MyBB\Cargo\Repository;
use MyBB\Cargo\RepositoryInterface;
use MyBB\Utilities\FileStamp;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\Utilities\Stopwatch\Stopwatch;
use MyBB\View\Locator\ViewletLocator;
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

        $managedValueRepository = $this->viewlet->getManagedValueRepository();

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
                    fn (string $identifier) => $this->getRepositoryInViewlet(
                        $this->viewlet->getAncestryViewlet($identifier)
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

    public function getResolvedRepository(string|ViewletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ViewletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getResolvedRepository($key);
    }

    public function resolveRepository(string|ViewletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ViewletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::resolveRepository($key);
    }

    public function queryRepository(string|ViewletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ViewletLocator) {
            $locator = $key;
            $key = $key->getNamespaceRelativeIdentifier();
        } else {
            $locator = ViewletLocator::fromNamespaceRelativeIdentifier(
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

    public function getClosestEntityAncestorRepository(string|ViewletLocator $key): ?RepositoryInterface
    {
        if ($key instanceof ViewletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getClosestEntityAncestorRepository($key);
    }

    /**
     * @return iterable<RepositoryInterface>
     */
    public function getEntityAncestorRepositories(string|ViewletLocator $key): iterable
    {
        if ($key instanceof ViewletLocator) {
            $key = $key->getNamespaceRelativeIdentifier();
        }

        return parent::getEntityAncestorRepositories($key);
    }

    /**
     * Returns the concrete, non-inheritance-aware Repository associated with the concrete Viewlet.
     *
     * @override decorated
     */
    public function getOwnRepository(): RepositoryInterface
    {
        return $this->getRepositoryInViewlet(
            $this->viewlet->getOwnViewlet()
        );
    }

    /**
     * @override decorated
     */
    public function stampValid(array $stamp): bool
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
    public function getRepositories(): array
    {
        $results = [];

        $viewlets = $this->viewlet->getViewletsByNamespace($this->namespace);

        foreach ($viewlets as $viewlet) {
            $results[$viewlet->getIdentifier()] = $this->getRepositoryInViewlet($viewlet);
        }

        return $results;
    }
}
