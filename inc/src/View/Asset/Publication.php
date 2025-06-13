<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use MyBB\Stopwatch\Stopwatch;
use MyBB\View\Asset\Processor\Processor;
use MyBB\View\Asset\Processor\ScssProcessor;
use MyBB\View\HierarchicalResource;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Optimization;
use MyBB\View\Resource;
use MyBB\View\ResourceLanguage;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\ThemeletInterface;

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
     * Resources declared as contributing to the converted Asset.
     *
     * @var array<string, array{
     *   themelet: string,
     *   subPath: string,
     * }>
     */
    private array $sources = [];

    /**
     * @param Processor[] $processors
     */
    public function __construct(
        private readonly ThemeletAsset $asset,
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
     * Returns a list of Resources effectively used as a source for a published Asset.
     */
    public static function getPublishedAssetResources(ThemeletAsset $asset): ?array
    {
        return $asset->getThemelet()->getAssetPublicationData($asset)['sources'] ?? null;
    }

    /**
     * Returns a list of Assets published using the provided Resource.
     */
    public static function getAssetsPublishedUsingResource(Resource $resource, ThemeletInterface $themelet): array
    {
        $assets = [];

        foreach ($themelet->getAssetPublicationData() as $namespaceAssetData) {
            foreach ($namespaceAssetData as $assetLocatorString => $assetData) {
                $assetSourceSignatures = $assetData['sources'] ?? [];

                if (in_array(self::getSourceSignature($resource), $assetSourceSignatures)) {
                    $assetLocator = ThemeletLocator::fromString($assetLocatorString);

                    $assets[$assetLocatorString] = new ThemeletAsset($assetLocator, $themelet);
                }
            }
        }

        return $assets;
    }

    /**
     * Returns metadata identifying the given source's origin.
     */
    public static function getSourceSignature(Resource $resource): array
    {
        return [
            'themelet' =>
                (
                    $resource instanceof HierarchicalResource
                        ? $resource->getResolved()
                        : $resource
                )
                ->getThemelet()
                ->getIdentifier(),
            'subPath' => $resource->getLocator()->getSubPath(),
        ];
    }

    /**
     * Whether the given Asset can be published as-is.
     */
    public static function isPlain(ThemeletAsset $asset): bool
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
     * Returns the Base Processor necessary to prepare the Asset for usage.
     *
     * @return ?class-string<static>
     */
    private static function getBaseProcessor(ThemeletAsset $asset): ?string
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

        $publishedFileTime = filemtime($path);

        if ($publishedFileTime === false) {
            return true;
        }

        if (
            $this->optimization->getDirective('publication.resolutionValidation') ||
            $this->optimization->getDirective('publication.sourceValidation')
        ) {
            $sourceResources = self::getPublishedAssetResources($this->asset);

            if ($sourceResources === null) {
                return true;
            }

            foreach ($sourceResources as $sourceResource) {
                $resource = $this->asset->getThemelet()->getResource(
                    $this->asset->getLocator()->getSibling($sourceResource['subPath'])
                );

                if (
                    $this->optimization->getDirective('publication.resolutionValidation') &&
                    (
                        !$resource->exists() ||
                        (
                            $resource instanceof HierarchicalResource &&
                            $resource->getResolved()->getThemelet()->getIdentifier() !== $sourceResource['themelet']
                        )
                    )
                ) {
                    $resource->resolve();

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
     * Processes the Asset and writes to resulting content to the Asset file.
     *
     * @param bool $force Whether to proceed even if the Asset is determined up-to-date.
     */
    public function publish(bool $force = false): bool
    {
        if (!$force && !$this->needsUpdate()) {
            return false;
        }

        $path = $this->asset->getAbsolutePath();

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        $fh = fopen($path, 'c');

        if (!$fh) {
            return false;
        }

        $result = false;

        if (
            flock($fh, LOCK_EX | LOCK_NB, $wasLocked) ||
            flock($fh, LOCK_EX)
        ) {
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
                        $this->asset->getThemelet()->setAssetPublicationData($this->asset, [
                            'sources' => $this->sources,
                        ]);
                    }
                } finally {
                    $stopwatchPeriod?->stop();
                }
            }

            flock($fh, LOCK_UN);
        }

        fclose($fh);

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

        $this->sources[] = self::getSourceSignature($resource);
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
