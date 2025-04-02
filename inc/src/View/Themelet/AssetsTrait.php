<?php

declare(strict_types=1);

namespace MyBB\View\Themelet;

use MyBB\View\Asset\Asset;
use MyBB\View\Locator\Locator;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\NamespaceCargo\Asset\Repository as AssetRepository;
use MyBB\View\Themelet\NamespaceCargo\Repository as NamespaceCargoRepository;

trait AssetsTrait
{
    /**
     * Asset Repositories by namespace.
     *
     * @var array<string, NamespaceCargoRepository>
     */
    private array $assetRepositories = [];

    public function getAssetRepository(string $namespace): NamespaceCargoRepository
    {
        return $this->assetRepositories[$namespace] ??=
            new AssetRepository($this, $namespace);
    }

    public function getAsset(
        Locator $locator,
        ?string $declarationNamespace = null,
        ?ResourceType $type = null,
    ): Asset
    {
        return Asset::fromLocator(
            locator: $locator,
            themelet: $this,
            declarationNamespace: $declarationNamespace,
            type: $type,
        );
    }

    public function getAssetPropertiesOfType(string $namespace, ResourceType $type): array
    {
        return array_filter(
            $this->getAssetProperties($namespace),
            fn ($identifier) => ResourceType::tryFromFilename($identifier) === $type,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function getAssetProperties(string $namespace): array
    {
        $repository = $this->getAssetRepository($namespace);

        return $repository->getEntityProperties();
    }
}
