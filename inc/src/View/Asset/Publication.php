<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use MyBB\Utilities\Stopwatch\Stopwatch;
use MyBB\View\Asset\Processor\Processor;
use MyBB\View\Asset\Processor\ScssProcessor;
use MyBB\View\HierarchicalResource;
use MyBB\View\Locator\Exception as LocatorException;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\Optimization;
use MyBB\View\Resource;
use MyBB\View\ResourceLanguage;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\ViewletInterface;
use TypeError;

/**
 * Prepares an Asset for web usage.
 *
 * Applies the base and provided Processors to source content, and writes the Asset file.
 */
class Publication
{
    /**
     * Types of Resources that can be used as a source for Assets.
     */
    public const PUBLISHABLE_RESOURCE_TYPES = [
        ResourceType::IMAGE,
        ResourceType::STYLE,
        ResourceType::SCRIPT,
    ];

    /**
     * Signatures of Resources declared as contributing to the converted Asset, by Locator string.
     *
     * @var array<string, array{
     *   viewlet: string,
     * }>
     */
    private array $sourceSignatures = [];

    /**
     * @param list<class-string<Processor>> $processors
     */
    public function __construct(
        private readonly ViewletAsset $asset,
        public readonly Filesystem $filesystem,
        public readonly Optimization $optimization,
        private array $processors = [],
        public readonly ?Stopwatch $stopwatch = null,
    )
    {
        $baseProcessor = self::getBaseProcessor($asset);

        if ($baseProcessor) {
            array_unshift($this->processors, $baseProcessor);
        }
    }

    /**
     * Returns a list of Resources effectively used as a source for a published Asset, linked to the Asset's Viewlet.
     *
     * @return list<Resource>
     */
    public static function getPublishedAssetResources(ViewletAsset $asset): array
    {
        return array_map(
            fn (string $locatorString) => self::getAssetSourceFromLocatorString($asset, $locatorString),
            array_keys(self::getPublishedAssetSourceSignatures($asset) ?? []),
        );
    }

    /**
     * Returns Assets published using the provided Resource, indexed by Locator string.
     *
     * @return array<string, ViewletAsset>
     */
    public static function getAssetsPublishedUsingResource(Resource $resource, ViewletInterface $viewlet): array
    {
        $assets = [];

        $locatorString = $resource->getLocator()->getString();

        foreach ($viewlet->getAssetPublicationData() as $assetLocatorString => $assetData) {
            $assetSourceSignatures = $assetData['sources'] ?? [];

            if (array_key_exists($locatorString, $assetSourceSignatures)) {
                $assets[$assetLocatorString] = new ViewletAsset(
                    ViewletLocator::fromString($assetLocatorString),
                    $viewlet,
                );
            }
        }

        return $assets;
    }

    /**
     * Whether the given Asset can be published as-is.
     */
    public static function isPlain(ViewletAsset $asset): bool
    {
        return self::getBaseProcessor($asset) === null;
    }

    /**
     * Whether the given Resource can be published or used as a source for Assets.
     */
    public static function resourcePublishable(Resource $resource): bool
    {
        return in_array($resource->getType(), self::PUBLISHABLE_RESOURCE_TYPES);
    }

    /**
     * Returns metadata identifying the given source's origin.
     */
    private static function getSourceSignature(Resource $resource): array
    {
        return [
            'viewlet' =>
                (
                    $resource instanceof HierarchicalResource
                        ? $resource->getResolved()
                        : $resource
                )
                    ->getViewlet()
                    ->getIdentifier(),
        ];
    }

    /**
     * Returns a Resource corresponding to the given Asset and source Locator string, linked to the Asset's Viewlet.
     */
    private static function getAssetSourceFromLocatorString(ViewletAsset $asset, string $locatorString): Resource
    {
        return $asset->getViewlet()->getResource(
            ViewletLocator::fromString($locatorString)
        );
    }

    /**
     * Returns an array of source signatures for a published Asset, indexed by Locator string.
     *
     * @return ?array<string, array>
     */
    private static function getPublishedAssetSourceSignatures(ViewletAsset $asset): ?array
    {
        return $asset->getViewlet()->getAssetPublicationData($asset)['sources'] ?? null;
    }

    /**
     * Returns the Base Processor necessary to prepare the Asset for usage.
     *
     * @return ?class-string<Processor>
     */
    private static function getBaseProcessor(ViewletAsset $asset): ?string
    {
        return match ($asset->getResource()->getLanguage()) {
            ResourceLanguage::SASS,
            ResourceLanguage::SCSS
                => ScssProcessor::class,
            default => null,
        };
    }

    /**
     * Whether the given Asset should be re-published.
     *
     * @note May result in false negative for source files modified successively within 1 second.
     */
    public function needsUpdate(): bool
    {
        $path = $this->asset->getAbsolutePath();

        if (!$this->filesystem->isFile($path)) {
            return true;
        }

        $publishedFileTime = $this->filesystem->lastModified($path);

        if ($publishedFileTime === false) {
            return true;
        }

        if (
            $this->optimization->getDirective('publication.resolutionValidation') ||
            $this->optimization->getDirective('publication.sourceValidation')
        ) {
            $sourceSignatures = self::getPublishedAssetSourceSignatures($this->asset);

            if ($sourceSignatures === null) {
                return true;
            }

            foreach ($sourceSignatures as $locatorString => $sourceSignature) {
                try {
                    $resource = self::getAssetSourceFromLocatorString($this->asset, $locatorString);
                } catch (TypeError | LocatorException) {
                    return true;
                }

                if (
                    $this->optimization->getDirective('publication.resolutionValidation') &&
                    (
                        !$resource->exists() ||
                        (
                            $resource instanceof HierarchicalResource &&
                            $sourceSignature !== self::getSourceSignature($resource)
                        )
                    )
                ) {
                    if ($resource instanceof HierarchicalResource) {
                        $resource->resolve();
                    }

                    return true;
                } elseif (
                    $this->optimization->getDirective('publication.sourceValidation') &&
                    $resource->getModificationTime() > $publishedFileTime
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Processes the Asset and writes the resulting content to the Asset file.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publish(bool $force = false): bool
    {
        if (!$force && !$this->needsUpdate()) {
            return false;
        }

        $path = $this->asset->getAbsolutePath();

        $this->filesystem->ensureDirectoryExists(
            dirname($path)
        );

        $fh = fopen($path, 'cb');

        if (!$fh) {
            return false;
        }

        $result = false;

        try {
            if (
                flock($fh, LOCK_EX | LOCK_NB, $wasLocked) ||
                flock($fh, LOCK_EX)
            ) {
                try {
                    if (
                        !$wasLocked ||
                        ($force || $this->needsUpdate())
                    ) {
                        $stopwatchPeriod = $this->stopwatch?->start(
                            $this->asset->getLocator()->getString(),
                            'core.view.asset.publish',
                        );

                        try {
                            $content = $this->getProcessedContent(
                                $this->getContent()
                            );

                            $result = $this->asset->write($content, $fh);

                            if ($result === true) {
                                $this->asset->getViewlet()->setAssetPublicationData($this->asset, [
                                    'sources' => $this->sourceSignatures,
                                ]);
                            }
                        } finally {
                            $stopwatchPeriod?->stop();
                        }
                    }
                } finally {
                    flock($fh, LOCK_UN);
                }
            }
        } finally {
            fclose($fh);
        }

        return $result;
    }

    /**
     * Registers the given Resource as contributing to the resulting Asset.
     */
    public function addSource(Resource $resource): void
    {
        if (!self::resourcePublishable($resource)) {
            throw new InvalidArgumentException('Cannot use Resource `' . $resource->getLocator()->getString() . '` as a source for Asset');
        }

        if ($resource->getNamespace() !== $this->asset->getNamespace()) {
            throw new InvalidArgumentException('Cannot use Resource in namespace `' . $resource->getNamespace() . '` as a source for Asset in namespace `' . $this->asset->getNamespace() . '`');
        }

        if (!$resource->exists()) {
            throw new InvalidArgumentException('Cannot use non-existent Resource `' . $resource->getLocator()->getString() . '` as a source for Asset');
        }

        $this->sourceSignatures[$resource->getLocator()->getString()] = self::getSourceSignature($resource);
    }

    /**
     * Returns the initial content from the associated Resource.
     */
    private function getContent(): string
    {
        $resource = $this->asset->getResource();

        $this->addSource($resource);

        return $resource->getContent();
    }

    /**
     * Returns content processed by the configured Processors.
     */
    private function getProcessedContent(string $content): string
    {
        foreach ($this->processors as $processor) {
            $processor = new $processor($this, $this->asset);

            $processor->setInputContent($content);

            $content = $processor->getOutputContent();
        }

        return $content;
    }
}
