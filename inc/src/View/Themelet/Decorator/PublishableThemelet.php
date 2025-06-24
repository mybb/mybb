<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\Decorator;

use Exception;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\View\Asset\Asset;
use MyBB\View\Asset\Publication;
use MyBB\View\Asset\ThemeletAsset;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Optimization;
use MyBB\View\Resource;
use MyBB\View\ResourceType;

use function MyBB\app;

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
     * When to publish Asset files.
     *
     * @var self::PUBLISH_*
     */
    public int $publishMode;

    /**
     * Publication data, indexed by namespace, and Asset's Themelet Locator.
     *
     * @var array<string, ManagedValue<array<string, array{
     *   sources: array{
     *     themelet: string,
     *     subPath: string,
     *   }
     * }>>>
     */
    private array $assetPublicationData = [];

    /**
     * Asset objects on which `Publication::publish()` was already called.
     *
     * @var array<string, ThemeletAsset>
     */
    private array $publishedAssets = [];

    public function __construct(
        private readonly Optimization $optimization,
    )
    {
        $managedValueRepository = $this->getManagedValueRepository();

        foreach ($this->getNamespaces() as $namespace) {
            $this->assetPublicationData[$namespace] = $managedValueRepository->create([
                'publication',
                $namespace,
            ])
                ->withDefault([])
            ;
        }

        $this->publishMode = $optimization->getDirective('publication.publishMode');
    }

    /**
     * @override decorated scope
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
     * Publishes and returns an Asset object with the Themelet's context.
     */
    public function getPublishedAsset(
        Locator $locator,
        ?string $declarationNamespace = null,
        ?ResourceType $type = null,
    ): Asset
    {
        if ($locator instanceof ThemeletLocator) {
            $locatorString = $locator->getString();

            if (
                $this->optimization->getDirective('publication.runtimeCache') === false ||
                !array_key_exists($locatorString, $this->publishedAssets)
            ) {
                $asset = new ThemeletAsset($locator, $this);

                $this->publishThemeletAsset($asset);
            }

            return $this->publishedAssets[$locatorString];
        } else {
            return $this->getAsset(
                locator: $locator,
                declarationNamespace: $declarationNamespace,
                type: $type,
            );
        }
    }

    /**
     * Publishes all Themelet Assets.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publishAssets(bool $force = false): void
    {
        foreach ($this->getPublishableThemeletAssets() as $asset) {
            $this->publishThemeletAsset($asset, $force);
        }
    }

    /**
     * Publishes Assets generated from, or previously published using, the given Resource.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publishAssetsFromResource(Resource $resource, bool $force = false): void
    {
        foreach ($this->getAssetsFromResource($resource) as $asset) {
            $this->publishThemeletAsset($asset, $force);
        }
    }

    /**
     * Publishes the given Themelet Asset.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publishThemeletAsset(ThemeletAsset $asset, bool $force = false): void
    {
        if ($force || $this->publishMode !== self::PUBLISH_NEVER) {
            $publication = app()->make(Publication::class, [
                'asset' => $asset,
            ]);

            $publication->publish($force || $this->publishMode === self::PUBLISH_ALWAYS);

            copy_file_to_cdn($asset->getAbsolutePath());

            $this->publishedAssets[$asset->getLocator()->getString()] = $asset;
        }
    }

    /**
     * Return Themelet Assets publishable from, or published using, the provided Resource.
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
     * Returns Themelet Assets that can be published.
     *
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
                array_diff_key(
                    $sourceResources ?? $this->getPublishableResources(),
                    $claimedResources,
                )
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
     * Returns Assets that could be published without being referenced in the properties file.
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

    /**
     * Returns the base path to published files, relative to the MyBB root directory.
     */
    public function getPublishingPath(): string
    {
        $extension = $this->getExtension();

        if ($extension === null) {
            throw new Exception('Cannot use publishing path for non-Extension Themelet');
        }

        return ThemeletAsset::WEB_ROOT_RELATIVE_BASE_PATH . $extension->getPackageName();
    }

    /**
     * Returns metadata related to the most recent publication of the given Themelet Asset.
     */
    public function getAssetPublicationData(?ThemeletAsset $asset = null): ?array
    {
        if ($asset === null) {
            return array_merge(
                ...array_map(
                    fn (ManagedValue $publicationData) => $publicationData->get(),
                    $this->assetPublicationData,
                )
            );
        } else {
            return $this->assetPublicationData[$asset->getNamespace()]?->getNested(
                [$asset->getLocator()->getString()],
            );
        }
    }

    /**
     * Sets metadata related to the most recent publication of the given Themelet Asset.
     */
    public function setAssetPublicationData(ThemeletAsset $asset, array $data): void
    {
        $this->assetPublicationData[$asset->getNamespace()]->setNested(
            [$asset->getLocator()->getString()],
            $data,
        );
    }
}
