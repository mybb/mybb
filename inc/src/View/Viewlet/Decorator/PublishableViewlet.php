<?php

declare(strict_types=1);

namespace MyBB\View\Viewlet\Decorator;

use InvalidArgumentException;
use LogicException;
use MyBB\Utilities\ManagedValue\ManagedValue;
use MyBB\View\Asset\Asset;
use MyBB\View\Asset\Publication;
use MyBB\View\Asset\ViewletAsset;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Optimization;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\NamespaceType;

use function MyBB\app;

/**
 * Adds asset generation features to a Viewlet.
 */
class PublishableViewlet extends ViewletDecorator
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
     * Publication data, indexed by namespace, and Asset's Viewlet Locator.
     *
     * @var array<string, ManagedValue<array<string, array{
     *   sources: array{
     *     viewlet: string,
     *     subPath: string,
     *   }
     * }>>>
     */
    private array $assetPublicationData = [];

    /**
     * Asset objects on which `Publication::publish()` was already called.
     *
     * @var array<string, ViewletAsset>
     */
    private array $publishedAssets = [];

    public function __construct(
        private readonly Optimization $optimization,
    ) {
        $managedValueRepository = $this->getManagedValueRepository();

        foreach ($this->getNamespaces() as $namespace) {
            $this->assetPublicationData[$namespace] = $managedValueRepository->create([
                'publication',
                $namespace,
            ])
                ->withDefault([])
                ->withBuild(
                    function (&$stamp) use ($namespace) {
                        $stamp = $this->getAssetRepository($namespace)->getStamp();

                        return [];
                    },
                )
                ->withStampValidation(
                    $this->getAssetRepository($namespace)->stampValid(...),
                )
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
    ): Asset {
        return Asset::fromLocator(
            locator: $locator,
            viewlet: $this,
            declarationNamespace: $declarationNamespace,
            type: $type,
        );
    }

    /**
     * Publishes and returns an Asset object with the Viewlet's context.
     */
    public function getPublishedAsset(
        Locator $locator,
        ?string $declarationNamespace = null,
        ?ResourceType $type = null,
    ): Asset {
        if ($locator instanceof ViewletLocator) {
            $locatorString = $locator->getString();

            if (
                $this->optimization->getDirective('publication.runtimeCache') &&
                array_key_exists($locatorString, $this->publishedAssets)
            ) {
                $asset = $this->publishedAssets[$locatorString];
            } else {
                $asset = new ViewletAsset($locator, $this);

                $this->publishAsset($asset);
            }
        } else {
            $asset = $this->getAsset(
                locator: $locator,
                declarationNamespace: $declarationNamespace,
                type: $type,
            );
        }

        return $asset;
    }

    /**
     * Returns a Viewlet Asset that may be published at the given web root-relative public path.
     */
    public function getAssetFromPublicPath(string $path): ?ViewletAsset
    {
        $extension = $this->getExtension();

        if ($extension === null) {
            throw new LogicException('Cannot use public path for non-Extension Viewlet');
        }


        $locator = ViewletAsset::getLocatorFromPublicPath($path, $packageName);

        if ($locator === null) {
            return null;
        }

        if (!NamespaceType::namespaceValid($locator->getNamespace(), $extension)) {
            throw new InvalidArgumentException('Invalid namespace for path `' . $path . '`');
        }

        if ($packageName !== $extension->getPackageName()) {
            throw new InvalidArgumentException('Viewlet path mismatch for path `' . $path . '`');
        }

        return new ViewletAsset($locator, $this);
    }

    /**
     * Publishes all Viewlet Assets.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publishAssets(bool $force = false): void
    {
        foreach ($this->getPublishableAssets() as $asset) {
            $this->publishAsset($asset, $force);
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
            $this->publishAsset($asset, $force);
        }
    }

    /**
     * Publishes the given Viewlet Asset.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publishAsset(ViewletAsset $asset, bool $force = false): void
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
     * Return Viewlet Assets publishable from, or published using, the provided Resource.
     *
     * @return ViewletAsset[]
     */
    public function getAssetsFromResource(Resource $resource): array
    {
        if (!Publication::resourcePublishable($resource)) {
            return [];
        }

        return array_merge(
            $this->getPublishableAssets([
                $resource->getLocator()->getString() => $resource,
            ]),
            Publication::getAssetsPublishedUsingResource($resource, $this),
        );
    }

    /**
     * Returns Viewlet Assets that can be published.
     *
     * @param ?array<string, Resource> $sourceResources
     * @return ViewletAsset[]
     */
    public function getPublishableAssets(?array $sourceResources = null): array
    {
        foreach ($sourceResources ?? [] as $sourceResource) {
            if (!Publication::resourcePublishable($sourceResource)) {
                throw new LogicException('Cannot use ' . __METHOD__ . ' with non-publishable Resource');
            }
        }


        $explicitlyPublishableAssets = $this->getExplicitlyPublishableAssets($sourceResources);

        $claimedResources = [];

        foreach ($explicitlyPublishableAssets as $asset) {
            $claimedResources[$asset->getResource()->getLocator()->getString()] = $asset->getResource();
        }

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
     * @param ?array<string, Resource> $sourceResources
     * @return ViewletAsset[]
     */
    public function getExplicitlyPublishableAssets(?array $sourceResources = null): array
    {
        $assets = [];

        if ($sourceResources === null) {
            $sourceResources = $this->getPublishableResources();
            $namespaces = $this->getNamespaces();
        } else {
            foreach ($sourceResources as $sourceResource) {
                if (!Publication::resourcePublishable($sourceResource)) {
                    throw new LogicException('Cannot use ' . __METHOD__ . ' with non-publishable Resource');
                }
            }

            $namespaces = array_map(
                fn (Resource $resource) => $resource->getNamespace(),
                $sourceResources,
            );
        }

        foreach ($namespaces as $namespace) {
            foreach ($this->getAssetProperties($namespace) as $identifier => $asset) {
                $locator = Locator::fromNamespaceRelativeIdentifier($namespace, $identifier);

                if ($locator instanceof ViewletLocator) {
                    $asset = $this->getAsset($locator);

                    if (array_key_exists($asset->getResource()->getLocator()->getString(), $sourceResources)) {
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
     * @param ?array<string, Resource> $sourceResources
     * @return ViewletAsset[]
     */
    public function getImplicitlyPublishableAssets(?array $sourceResources = null): array
    {
        foreach ($sourceResources ?? [] as $sourceResource) {
            if (!Publication::resourcePublishable($sourceResource)) {
                throw new LogicException('Cannot use ' . __METHOD__ . ' with non-publishable Resource');
            }
        }


        $assets = [];

        foreach ($sourceResources ?? $this->getPublishableResources() as $resource) {
            $resourceLocator = $resource->getLocator();

            $asset = new ViewletAsset($resourceLocator, $this);

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
    public function getPublishingPath(?string $namespace = null, ?ResourceType $resourceType = null): string
    {
        $extension = $this->getExtension();

        if ($extension === null) {
            throw new LogicException('Cannot use publishing path for non-Extension Viewlet');
        }

        $components = [
            ViewletAsset::WEB_ROOT_RELATIVE_BASE_PATH . $extension->getPackageName(),
        ];

        if ($namespace !== null) {
            $components[] = $namespace;
        }

        if ($resourceType !== null) {
            if ($namespace === null) {
                throw new InvalidArgumentException('Resource Type must be provided with namespace argument');
            }

            $components[] = $resourceType->getPlural();
        }

        return implode('/', $components);
    }

    /**
     * Returns metadata related to the most recent publication of the given Viewlet Asset.
     */
    public function getAssetPublicationData(?ViewletAsset $asset = null): ?array
    {
        if ($asset === null) {
            return array_merge(
                ...array_values(
                    array_map(
                        fn (ManagedValue $publicationData) => $publicationData->get(),
                        $this->assetPublicationData,
                    )
                )
            );
        } else {
            return $this->assetPublicationData[$asset->getNamespace()]?->getNested(
                [$asset->getLocator()->getString()],
            );
        }
    }

    /**
     * Sets metadata related to the most recent publication of the given Viewlet Asset.
     */
    public function setAssetPublicationData(ViewletAsset $asset, array $data): void
    {
        $this->assetPublicationData[$asset->getNamespace()]->setNested(
            [$asset->getLocator()->getString()],
            $data,
        );
    }
}
