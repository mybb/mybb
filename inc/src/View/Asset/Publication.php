<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use InvalidArgumentException;
use MyBB\Stopwatch\Stopwatch;
use MyBB\View\Asset\Processor\Processor;
use MyBB\View\Asset\Processor\ScssProcessor;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Resource;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\ThemeletInterface;

use function MyBB\app;

/**
 * Prepares Theme Assets for web usage.
 */
class Publication
{
    public const PUBLISHABLE_RESOURCE_TYPES = [
        ResourceType::IMAGE,
        ResourceType::STYLE,
        ResourceType::SCRIPT,
    ];

    private readonly ThemeletAsset $asset;

    /**
     * @var Processor[]
     */
    private array $processors;

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
     * @note May result in false negative for source files modified successively within 1 second.
     */
    public static function needsUpdate(ThemeletAsset $asset): bool
    {
        $path = $asset->getAbsolutePath();

        $publishedFileTime = filemtime($path);

        if ($publishedFileTime === false) {
            return true;
        }

        $sourceResources = self::getPublishedAssetResources($asset);

        if ($sourceResources === null) {
            return true;
        }

        foreach ($sourceResources as $sourceResource) {
            $resource = $asset->getThemelet()->getExistingResource(
                $asset->getLocator()->getSibling($sourceResource['subPath'])
            );

            if (
                $resource->getThemelet()->getIdentifier() !== $sourceResource['themelet'] ||
                $resource->getModificationTime() > $publishedFileTime
            ) {
                return true;
            }
        }

        return false;
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

        foreach ($themelet->getAssetPublicationData() as $assetLocatorString => $assetData) {
            $assetSourceSignatures = $assetData['sources'] ?? [];

            if (in_array(self::getSourceSignature($resource), $assetSourceSignatures)) {
                $assetLocator = ThemeletLocator::fromString($assetLocatorString);

                $assets[$assetLocatorString] = new ThemeletAsset($assetLocator, $themelet);
            }
        }

        return $assets;
    }

    public static function getSourceSignature(Resource $resource): array
    {
        return [
            'themelet' => $resource->getThemelet()->getIdentifier(),
            'subPath' => $resource->getLocator()->getSubPath(),
        ];
    }

    public static function isPlain(ThemeletAsset $asset): bool
    {
        return self::getBaseProcessor($asset) === null;
    }

    public static function resourcePublishable(Resource $resource): bool
    {
        return in_array($resource->getType(), self::PUBLISHABLE_RESOURCE_TYPES);
    }

    /**
     * @return ?class-string<static>
     */
    private static function getBaseProcessor(ThemeletAsset $asset): ?string
    {
        $sourceExtension = pathinfo($asset->getResource()->getFilename(), PATHINFO_EXTENSION);

        return match ($sourceExtension) {
            'sass', 'scss' => ScssProcessor::class,
            default => null,
        };
    }

    /**
     * @param ThemeletAsset $asset
     * @param Processor[] $processors
     */
    public function __construct(ThemeletAsset $asset, array $processors = [])
    {
        $this->asset = $asset;
        $this->processors = $processors;

        $baseProcessor = self::getBaseProcessor($asset);

        if ($baseProcessor) {
            array_unshift($this->processors, $baseProcessor);
        }
    }

    public function publish(bool $force = false): bool
    {
        if (!$force && !static::needsUpdate($this->asset)) {
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
                ($force || static::needsUpdate($this->asset))
            ) {
                $stopwatchPeriod = app(Stopwatch::class)->start(
                    $this->asset->getLocator()->getString(),
                    'core.view.asset.publish',
                );

                try {
                    $content = $this->getProcessedContent(
                        $this->getContent()
                    );

                    $result = $this->asset->write($content, false);

                    if ($result === true) {
                        $this->asset->getThemelet()->setAssetPublicationData($this->asset, [
                            'sources' => $this->sources,
                        ]);
                    }
                } finally {
                    $stopwatchPeriod->stop();
                }
            }

            flock($fh, LOCK_UN);
        }

        fclose($fh);

        return $result;
    }

    public function addSource(Resource $resource): void
    {
        if (!self::resourcePublishable($resource)) {
            throw new InvalidArgumentException('Cannot use Resource `' . $resource->getLocator()->getString() . '` as a source for Asset');
        }

        $this->sources[] = self::getSourceSignature($resource);
    }

    private function getContent(): string
    {
        $resource = $this->asset->getResource();

        $this->addSource($resource);

        return $resource->getContent();
    }

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
