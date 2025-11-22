<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use MyBB;
use MyBB\Cargo\EntityTrait;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\ViewletLocator;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\ViewletInterface;

use function MyBB\app;

abstract class Asset
{
    use EntityTrait;

    public static function fromLocator(
        Locator $locator,
        ?ViewletInterface $viewlet = null,
        ?string $declarationNamespace = null,
        ?ResourceType $type = null,
    ): static
    {
        return match (get_class($locator)) {
            ViewletLocator::class => new ViewletAsset($locator, $viewlet),
            StaticLocator::class => new StaticAsset($locator, $viewlet, $declarationNamespace, $type),
        };
    }

    /**
     * Returns Properties merged from multiple sources.
     *
     * @param array[] $properties
     */
    public static function getMergedProperties(array $properties): array
    {
        return array_merge_recursive(...$properties);
    }

    public function getLocator(): Locator
    {
        return $this->locator;
    }

    public function getViewlet(): ViewletInterface
    {
        return $this->viewlet;
    }

    public function getRepository(): RepositoryInterface
    {
        return $this->getViewlet()->getAssetRepository(
            $this->getEntityNamespace()
        );
    }

    public function getRepositoryKey(): string
    {
        return $this->getLocator()->getNamespaceRelativeIdentifier();
    }

    public function getUrl(bool $useCdn = true): string
    {
        // logic from MyBB::get_asset_url() may be moved to the View domain here

        $url = app(MyBB::class)->get_asset_url($this->getPublicPath(), $useCdn);

        return $url;
    }

    abstract public function getPublicPath(): string;

    abstract public function getType(): ?ResourceType;

    abstract protected function getEntityNamespace(): string;
}
