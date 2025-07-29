<?php

declare(strict_types=1);

namespace MyBB\View\Asset;

use MyBB;
use MyBB\Cargo\RepositoryInterface;
use MyBB\View\ResourceType;
use MyBB\View\Themelet\NamespaceCargo\EntityTrait;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Themelet\ThemeletInterface;

use function MyBB\app;

abstract class Asset
{
    use EntityTrait;

    public static function fromLocator(
        Locator $locator,
        ?ThemeletInterface $themelet = null,
        ?string $declarationNamespace = null,
        ?ResourceType $type = null,
    ): static
    {
        return match (get_class($locator)) {
            ThemeletLocator::class => new ThemeletAsset($locator, $themelet),
            StaticLocator::class => new StaticAsset($locator, $themelet, $declarationNamespace, $type),
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

    public function getThemelet(): ThemeletInterface
    {
        return $this->themelet;
    }

    public function getRepository(): RepositoryInterface
    {
        return $this->getThemelet()->getAssetRepository(
            $this->getEntityNamespace()
        );
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
