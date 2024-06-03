<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\Decorator;

use Exception;
use MyBB\Utilities\Hydrable\Hydrable;
use MyBB\View\Asset\Asset;
use MyBB\View\Asset\Publication;
use MyBB\View\Asset\ThemeletAsset;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;

use function MyBB\View\directive;

/**
 * Adds asset generation features to a Themelet.
 */
class PublishableThemelet extends ThemeletDecorator
{
    /**
     * Rely on generated Asset files without validation.
     */
    final public const PUBLISH_NEVER = 2;

    /**
     * Generate Asset files if existing ones are stale.
     */
    final public const PUBLISH_AUTO = 4;

    /**
     * Generate Asset files each time.
     */
    final public const PUBLISH_ALWAYS = 8;

    /**
     * @var self::PUBLISH_*
     */
    public int $publishMode;

    private Hydrable $publicationHydrable;

    public function __construct()
    {
        $hydrables = $this->getHydrableRepository();

        $this->publicationHydrable = $hydrables->add(
            new Hydrable(
                [],
                'publication',
            ),
        );

        $this->publishMode = directive('publication.publishMode');
    }

    /**
     * @override scope
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

    public function getPublishedAsset(
        Locator $locator,
        ?string $declarationNamespace = null,
        ?ResourceType $type = null,
    ): Asset
    {
        $asset = $this->getAsset(
            locator: $locator,
            declarationNamespace: $declarationNamespace,
            type: $type,
        );

        if ($asset instanceof ThemeletAsset) {
            $this->publishThemeletAsset($asset);
        }

        return $asset;
    }

    public function publishAssets(bool $force = false): void
    {
        foreach ($this->getPublishableThemeletAssets() as $asset) {
            $this->publishThemeletAsset($asset, $force);
        }
    }

    public function publishAssetsFromResource(Resource $resource, bool $force = false): void
    {
        foreach ($this->getAssetsFromResource($resource) as $asset) {
            $this->publishThemeletAsset($asset, $force);
        }
    }

    public function publishThemeletAsset(ThemeletAsset $asset, bool $force = false): void
    {
        if ($force || $this->publishMode !== self::PUBLISH_NEVER) {
            $publication = new Publication($asset);

            $publication->publish($force || $this->publishMode === self::PUBLISH_ALWAYS);

            copy_file_to_cdn($asset->getAbsolutePath());
        }
    }

    /**
     * Return Assets publishable from, or published using, the provided Resource.
     *
     * @return ThemeletAsset[]
     */
    public function getAssetsFromResource(Resource $resource): array
    {
        return array_merge(
            $this->getPublishableThemeletAssets([$resource]),
            Publication::getAssetsPublishedUsingResource($resource, $this),
        );
    }

    /**
     * @param ?Resource[] $sourceResources
     * @return ThemeletAsset[]
     */
    public function getPublishableThemeletAssets(?array $sourceResources = null): array
    {
        $explicitlyPublishableAssets = $this->getExplicitlyPublishableAssets($sourceResources);

        $claimedResources = array_map(
            fn (ThemeletAsset $asset) => $asset->getResource(),
            $explicitlyPublishableAssets,
        );

        return array_merge(
            $explicitlyPublishableAssets,
            $this->getImplicitlyPublishableAssets(
                array_diff_key($sourceResources, $claimedResources)
            ),
        );
    }

    /**
     * Returns Assets referenced in the properties file.
     *
     * @param ?Resource[] $sourceResources
     * @return ThemeletAsset[]
     */
    public function getExplicitlyPublishableAssets(?array $sourceResources = null): array
    {
        $assets = [];

        if ($sourceResources === null) {
            $sourceResources = $this->getPublishableResources();
            $namespaces = $this->getNamespaces();
        } else {
            $namespaces = array_map(
                fn (Resource $resource) => $resource->getNamespace(),
                $sourceResources,
            );
        }

        foreach ($namespaces as $namespace) {
            foreach ($this->getAssetProperties($namespace) as $identifier => $asset) {
                $locator = Locator::fromNamespaceRelativeIdentifier($namespace, $identifier);

                if ($locator instanceof ThemeletLocator) {
                    $asset = $this->getAsset($locator);

                    if (in_array($asset->getResource(), $sourceResources)) {
                        $assets[$locator->getString()] = $asset;
                    }
                }
            }
        }

        return $assets;
    }

    /**
     * Returns Assets that can be published without being referenced in the properties file.
     *
     * @param ?Resource[] $sourceResources
     * @return ThemeletAsset[]
     */
    public function getImplicitlyPublishableAssets(?array $sourceResources = null): array
    {
        $assets = [];

        foreach ($sourceResources ?? $this->getPublishableResources() as $resource) {
            $resourceLocator = $resource->getLocator();

            $asset = new ThemeletAsset($resourceLocator, $this);

            if (Publication::isPlain($asset)) {
                $assets[$resourceLocator->getString()] = $asset;
            }
        }

        return $assets;
    }

    /**
     * Returns Resources generally usable for publishing.
     *
     * @return array<string, Resource>
     */
    public function getPublishableResources(): array
    {
        return $this->getResources(resourceTypes: Publication::PUBLISHABLE_RESOURCE_TYPES);
    }

    public function getPublishingPath(): string
    {
        $extension = $this->getExtension();

        if ($extension === null) {
            throw new Exception('Cannot use publishing path for non-Extension Themelet');
        }

        return ThemeletAsset::WEB_ROOT_RELATIVE_BASE_PATH . $extension->getPackageName();
    }

    public function getAssetPublicationData(?ThemeletAsset $asset = null): ?array
    {
        $items = $this->publicationHydrable->get();

        return $asset
            ? $items[$asset->getLocator()->getString()] ?? null
            : $items
        ;
    }

    public function setAssetPublicationData(ThemeletAsset $asset, array $data): void
    {
        $this->publicationHydrable->setNested([$asset->getLocator()->getString()], $data);
    }
}
