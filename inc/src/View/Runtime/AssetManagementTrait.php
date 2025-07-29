<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use Exception;
use MyBB\View\Asset\Asset;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\ResourceType;
use SplObjectStorage;

use function MyBB\View\template;

trait AssetManagementTrait
{
    /**
     * @var ResourceType[]
     */
    public const ATTACHABLE_TYPES = [
        ResourceType::STYLE,
        ResourceType::SCRIPT,
    ];

    /**
     * @var ResourceType[]
     */
    public const INSERTABLE_TYPES = [
        ResourceType::STYLE,
        ResourceType::SCRIPT,
    ];

    /**
     * @var array{
     *   script: string,
     *   action: string,
     * }
     */
    private array $context;

    /**
     * Published Assets for insertion into the DOM, by Locator string.
     *
     * @var array<string, Asset>
     */
    private array $publishedAssets = [];

    /**
     * Effective Asset Properties to use when inserting Assets into the DOM.
     *
     * Collected from static and runtime declarations.
     *
     * @var SplObjectStorage<Asset, array>
     */
    private SplObjectStorage $assetProperties;

    /**
     * Assets by type and Locator string.
     *
     * @var array<value-of<ResourceType>, array<string, Asset>
     */
    private array $attachedAssets = [];

    /**
     * Assets reported as included in the DOM, by Locator string.
     *
     * @var array<string, Asset>
     */
    private array $assetsInDom = [];

    private bool $attachedAssetsPopulatedFromThemelet = false;

    /**
     * @param array{
     *   script: string,
     *   actions?: string[],
     * } $conditions
     * @param array{
     *   script: string,
     *   action: string,
     * } $context
     * @return bool
     */
    public static function attachConditionsSatisfied(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (
                isset($condition['script']) &&
                (
                    $condition['script'] === 'global' ||
                    $condition['script'] === $context['script']
                ) &&
                (
                    !isset($condition['actions']) ||
                    in_array('global', $condition['actions']) ||
                    in_array($context['action'], $condition['actions'])
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Replaces placeholders with asset tags yet to be inserted into DOM.
     * Used for assets declared after the placeholder's template was rendered.
     */
    public function insertDeferredAttachedAssets(string $contents): string
    {
        foreach (self::ATTACHABLE_TYPES as $type) {
            $assets = $this->getAttachedAssets($type, inserting: true);

            $elements = array_map(
                fn (Asset $asset) => $this->getAssetHtml($asset),
                $assets,
            );

            $elementsHtml = implode($elements);

            $contents = str_replace(
                '<!-- deferred_attached_assets.' . $type->value . ' -->',
                $elementsHtml,
                $contents,
            );
        }

        return $contents;
    }

    /**
     * @param bool $inserting Get assets not yet inserted, and declare them as such.
     */
    public function getAttachedAssets(ResourceType $type, bool $inserting = false): array
    {
        if (!$this->attachedAssetsPopulatedFromThemelet) {
            $this->populateAttachedAssetsFromThemelet();

            $this->attachedAssetsPopulatedFromThemelet = true;
        }

        $assets = $this->attachedAssets[$type->value] ?? [];

        if ($inserting) {
            $assets = array_filter(
                $assets,
                fn (Asset $asset) => !in_array($asset, $this->assetsInDom),
            );

            array_map(
                fn (Asset $asset) => $this->assetsInDom[$asset->getLocator()->getString()] = $asset,
                $assets,
            );
        }

        return $assets;
    }

    public function populateAttachedAssetsFromThemelet(): void
    {
        foreach ($this->themelet->getCompositeAssetProperties() as $locatorString => $properties) {
            if ($this->assetApplicableThroughProperties($properties)) {
                $this->attachAsset(Locator::fromString($locatorString));
            }
        }
    }

    /**
     * @param string[] $dependentAncestors
     */
    public function attachAsset(
        Locator $locator,
        array $properties = [],
        ?ResourceType $type = null,
        array $dependentAncestors = [],
    ): Asset
    {
        $locatorString = $locator->getString([
            'type' => ThemeletLocator::COMPONENT_SET,
            'namespace' => ThemeletLocator::COMPONENT_SET,
        ]);

        $type ??= match (get_class($locator)) {
            StaticLocator::class => ResourceType::tryFromFilename($locator->getPath()),
            ThemeletLocator::class => $locator->getType(),
        };


        if ($type === null) {
            throw new Exception('Unknown Asset type (`' . $locatorString . '`)');
        }

        if (!in_array($type, static::ATTACHABLE_TYPES)) {
            throw new Exception('Cannot attach Asset of type `' . $type->value . '` (`' . $locatorString . '`)');
        }


        $asset = $this->getAsset($locator, $type);

        $this->addAssetProperties($asset, $properties);


        $this->attachAssetWithDependencies($asset, $dependentAncestors);

        return $asset;
    }

    /**
     * Returns the HTML to insert the Asset into the DOM, and sets it as inserted.
     *
     * @throws Exception if the given Asset cannot be inserted.
     */
    public function getAssetForInsertion(
        Locator $locator,
        array $properties = [],
        ?ResourceType $type = null,
    ): string
    {
        $locatorString = $locator->getString([
            'type' => ThemeletLocator::COMPONENT_SET,
            'namespace' => ThemeletLocator::COMPONENT_SET,
        ]);

        $type ??= match (get_class($locator)) {
            StaticLocator::class => ResourceType::tryFromFilename($locator->getPath()),
            ThemeletLocator::class => $locator->getType(),
        };


        if ($type === null) {
            throw new Exception('Unknown Asset type (`' . $locatorString . '`)');
        }

        if (!in_array($type, static::INSERTABLE_TYPES)) {
            throw new Exception('Cannot insert Asset of type `' . $type->value . '` (`' . $locatorString . '`)');
        }


        $asset = $this->getAsset($locator, $type);

        $this->addAssetProperties($asset, $properties);


        $this->assetsInDom[$locatorString] = $asset;

        return $this->getAssetHtml($asset);
    }

    /**
     * Adds the given Asset and its dependencies for managed insertion the DOM (if not already present).
     *
     * @param string[] $dependentAncestors Identifiers of dependent Assets passed in recursive calls.
     */
    private function attachAssetWithDependencies(Asset $asset, array $dependentAncestors = []): void
    {
        $locatorString = $asset->getLocator()->getString();

        if (isset($this->attachedAssets[$asset->getType()->value][$locatorString])) {
            return;
        }

        if (in_array($locatorString, $dependentAncestors)) {
            throw new Exception('Circular dependency declared for Asset `' . $locatorString . '`');
        }


        $dependentAncestors[] = $locatorString;

        $dependencies = $this->getAssetImmediateDependencies($asset->getLocator());

        foreach ($dependencies as $dependency) {
            $this->attachAsset($dependency, dependentAncestors: $dependentAncestors);
        }


        $this->attachedAssets[$asset->getType()->value][$locatorString] = $asset;
    }

    /**
     * Returns a published Asset with Properties initialized from the Themelet.
     */
    private function getAsset(Locator $locator, ResourceType $type): Asset
    {
        $locatorString = $locator->getString();

        if (array_key_exists($locatorString, $this->publishedAssets)) {
            $asset = $this->publishedAssets[$locatorString];
        } else {
            $asset = $this->themelet->getPublishedAsset(
                locator: $locator,
                type: $type,
            );

            $this->publishedAssets[$locatorString] = $asset;

            $this->addAssetProperties(
                $asset,
                $this->themelet->getCompositeAssetProperties($locator),
            );
        }

        return $asset;
    }

    private function assetApplicableThroughProperties(?array $properties = null): bool
    {
        return (
            isset($properties['attached_to']) &&
            static::attachConditionsSatisfied($properties['attached_to'], $this->context)
        );
    }

    /**
     * Returns an Asset's dependencies that should be attached before it.
     *
     * @return Locator[]
     */
    private function getAssetImmediateDependencies(Locator $locator): array
    {
        return array_map(
            fn (string $identifier) => Locator::fromDependencyIdentifier($identifier, $locator),
            $this->themelet->getCompositeAssetProperties($locator)['depends_on'] ?? [],
        );
    }

    /**
     * Adds extra Asset Properties accepted during runtime.
     */
    private function addAssetProperties(Asset $asset, array $properties): void
    {
        $this->assetProperties[$asset] = Asset::getMergedProperties([
            $this->assetProperties[$asset] ?? [],
            $properties,
        ]);
    }

    /**
     * Returns the HTML to insert the Asset into the DOM.
     */
    private function getAssetHtml(Asset $asset): string
    {
        $type = $asset->getType();

        if ($type === null) {
            throw new Exception('Unknown Asset type (`' . $asset->getLocator()->getString() . '`)');
        }

        if (!in_array($type, self::INSERTABLE_TYPES)) {
            throw new Exception('Cannot insert Asset of type `' . $type->value . '` (`' . $asset->getLocator()->getString() . '`)');
        }

        return template(
            'partials/' . $type->value . '.twig',
            [
                'asset' => [
                    'url' => $asset->getUrl(),
                    'attributes' => $this->assetProperties[$asset]['attributes'] ?? [],
                ],
            ],
        );
    }
}
