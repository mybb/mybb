<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\Decorator\Hierarchy;

use MyBB\Cargo\Decorator\RepositoryDecorator;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Themelet\AssetsTrait;
use MyBB\View\Themelet\NamespaceCargo\Asset\Repository as AssetRepository;
use MyBB\View\Themelet\NamespaceCargo\HierarchicalRepository;
use MyBB\View\Themelet\NamespaceCargo\Repository as NamespaceCargoRepository;

trait HierarchicalAssetsTrait
{
    use AssetsTrait; // override decorated scope for remaining methods

    /**
     * Asset Repositories by namespace.
     *
     * @var array<string, NamespaceCargoRepository>
     */
    private array $assetRepositories = [];

    public function getAssetRepository(string $namespace): RepositoryInterface
    {
        return $this->assetRepositories[$namespace] ??=
            RepositoryDecorator::decorate(
                new AssetRepository($this, $namespace),
                [
                    HierarchicalRepository::class,
                ],
            )
        ;
    }

    private function buildAssetProperties(): void
    {
        $this->getManagedValueRepository()->get('hierarchy.properties.assets')->clear();

        foreach ($this->getNamespaces() as $namespace) {
            $repository = $this->getAssetRepository($namespace);

            $repository->buildCache();
        }
    }
}
