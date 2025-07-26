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

    /**
     * Returns the Asset Repository for the given namespace.
     */
    public function getAssetRepository(string $namespace): NamespaceCargoRepository
    {
        return $this->assetRepositories[$namespace] ??=
            new AssetRepository($this, $namespace);
    }

    /**
     * Returns an Asset object with the Themelet's context.
     */
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

    /**
     * Returns Asset Properties by identifier, for Assets with the given Type.
     */
    public function getAssetPropertiesOfType(string $namespace, ResourceType $type): array
    {
        return array_filter(
            $this->getAssetProperties($namespace),
            fn ($identifier) => ResourceType::tryFromFilename($identifier) === $type,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Returns Asset Properties by identifier.
     *
     * @return array<string, array>
     */
    public function getAssetProperties(string $namespace): array
    {
        $repository = $this->getAssetRepository($namespace);

        return $repository->getEntityProperties();
    }
}
