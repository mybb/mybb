<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use MyBB;
use MyBB\Extensions\Plugin;
use MyBB\Extensions\Theme;
use MyBB\View\Optimization;
use MyBB\View\Themelet\Decorator\CompositeThemelet;
use MyBB\View\Themelet\Decorator\Hierarchy\HierarchicalThemelet;
use MyBB\View\Themelet\Decorator\PublishableThemelet;
use MyBB\View\Themelet\Decorator\ThemeletDecorator;
use MyBB\View\Themelet\ThemeletInterface;
use SplObjectStorage;

/**
 * Environment information and operations related to interface handling.
 */
class Runtime
{
    use AssetManagementTrait;
    use DataSharingTrait;
    use NamespacesTrait;

    public readonly ThemeletInterface $themelet;

    public function __construct(
        private readonly MyBB $mybb,
        private readonly Theme $theme,
        private readonly Optimization $optimization,
    )
    {
        $this->themelet = $this->getDecoratedThemelet();

        /* @see AssetManagementTrait */
        $this->assetProperties = new SplObjectStorage();

        if ($this->optimization->getDirective('publication.all')) {
            $this->themelet->publishAssets();
        }
    }

    private function getDecoratedThemelet(): ThemeletInterface
    {
        $themelet = $this->theme->getThemelet();

        // HierarchicalThemelet

        $themelet = ThemeletDecorator::decorate(
            $themelet,
            [
                HierarchicalThemelet::class,
            ],
        );

        $pluginThemelets = $this->getPluginThemelets();

        $themelet->setBaseThemelets($pluginThemelets);


        // PublishableThemelet, CompositeThemelet

        $themelet = ThemeletDecorator::decorate(
            $themelet,
            [
                PublishableThemelet::class,
                CompositeThemelet::class,
            ],
        );

        foreach ($pluginThemelets as $pluginThemelet) {
            foreach ($pluginThemelet->getNamespaces() as $namespace) {
                $themelet->applyNamespace($namespace);
            }
        }


        return $themelet;
    }

    /**
     * @return ThemeletInterface[]
     */
    private function getPluginThemelets(): array
    {
        return array_map(
            fn (string $codename) => Plugin::get($codename)->getThemelet(),
            $this->mybb->cache?->read('plugins')['active'] ?? [],
        );
    }
}
