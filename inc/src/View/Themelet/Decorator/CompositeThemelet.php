<?php

declare(strict_types=1);

namespace MyBB\View\Themelet\Decorator;

use MyBB\View\Asset\Asset;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ThemeletLocator;

/**
 * Merges common data from active namespaces.
 */
class CompositeThemelet extends ThemeletDecorator
{
    /**
     * Namespaces from which Resources are used.
     *
     * @var string[]
     */
    private array $appliedNamespaces = [];

    /**
     * Properties indexed by locator string.
     *
     * @var ?array<string, array>
     */
    private ?array $combinedAssetProperties;

    private bool $assetPropertiesPopulated = false;

    public function applyNamespace(string $name): void
    {
        if (!in_array($name, $this->appliedNamespaces)) {
            $this->appliedNamespaces[$name] = $name;

            $this->combinedAssetProperties = null; // rebuild needed
        }
    }

    public function getAppliedNamespaces(): array
    {
        return $this->appliedNamespaces;
    }

    /**
     * Returns Asset properties defined in all applied namespaces.
     */
    public function getCompositeAssetProperties(?Locator $selector = null): array
    {
        if ($selector === null) {
            if (!$this->assetPropertiesPopulated) {
                $this->populateAssetProperties();

                $this->assetPropertiesPopulated = true;
            }

            return $this->combinedAssetProperties;
        } else {
            if (!isset($this->combinedAssetProperties[$selector->getString()])) {
                $this->populateAssetProperties($selector);
            }

            return $this->combinedAssetProperties[$selector->getString()];
        }
    }

    private function populateAssetProperties(?Locator $locator = null): void
    {
        $sets = [];

        if ($locator === null) {
            foreach ($this->getAppliedNamespaces() as $namespace) {
                foreach ($this->getAssetProperties($namespace) as $identifier => $properties) {
                    $locator = Locator::fromNamespaceRelativeIdentifier($namespace, $identifier);

                    $sets[$locator->getString()][] = $properties;
                }
            }

            $this->combinedAssetProperties = array_map(
                Asset::getMergedProperties(...),
                $sets,
            );
        } else {
            foreach ($this->getAssetSourceNamespaces($locator) as $namespace) {
                $asset = $this->getAsset($locator, declarationNamespace: $namespace);

                $sets[] = $asset->getProperties();
            }

            $this->combinedAssetProperties[$locator->getString()] = Asset::getMergedProperties($sets);
        }
    }

    /**
     * Returns namespaces from which an Asset's properties may be retrieved.
     */
    private function getAssetSourceNamespaces(Locator $locator): array
    {
        return match (get_class($locator)) {
            StaticLocator::class => $this->getAppliedNamespaces(),
            ThemeletLocator::class => [$locator->getNamespace()],
        };
    }
}
