<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use Exception;
use MyBB\View\Asset\Asset;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\ResourceType;

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
     * @var array{
     *   script: string,
     *   action: string,
     * }
     */
    private array $context;

    /**
     * Assets by type and Locator string.
     *
     * @var array<value-of<ResourceType>, array<string, Asset>
     */
    private array $attachedAssets;

    private bool $attachedAssetsPopulated = false;

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
                fn (Asset $asset) => $asset->getHtml(),
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
        if (!$this->attachedAssetsPopulated) {
            $this->populateAttachedAssetsFromThemelet();

            $this->attachedAssetsPopulated = true;
        }

        $assets = $this->attachedAssets[$type->value] ?? [];

        if ($inserting) {
            $assets = array_filter(
                $assets,
                fn (Asset $asset) => $asset->insertedToDom === false,
            );

            array_map(
                fn (Asset $asset) => $asset->insertedToDom = true,
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

        if (in_array($locatorString, $dependentAncestors)) {
            throw new Exception('Circular dependency declared for Asset `' . $locatorString . '`');
        }


        if (isset($this->attachedAssets[$type->value][$locatorString])) {
            $asset = $this->attachedAssets[$type->value][$locatorString];
        } else {
            $dependentAncestors[] = $locatorString;

            $dependencies = $this->getAssetImmediateDependencies($locator);

            foreach ($dependencies as $dependency) {
                $this->attachAsset($dependency, dependentAncestors: $dependentAncestors);
            }


            $asset = $this->themelet->getPublishedAsset(
                locator: $locator,
                type: $type,
            );

            $asset->setCompositeProperties(
                $this->themelet->getCompositeAssetProperties($locator),
            );

            $this->attachedAssets[$type->value][$locatorString] = $asset;
        }

        $asset->setCompositeProperties($properties);

        return $asset;
    }

    public function assetApplicableThroughProperties(?array $properties = null): bool
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
            fn (string $identifier) =>
                Locator::fromDependencyIdentifier($identifier, $locator),
            $this->themelet->getCompositeAssetProperties($locator)['depends_on'] ?? [],
        );
    }
}
