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

    public ThemeletInterface $themelet;

    public function __construct(
        private readonly MyBB $mybb,
        private readonly Theme $theme,
        private readonly Optimization $optimization,
    )
    {
        $this->themelet = ThemeletDecorator::decorate(
            $this->theme->getThemelet(),
            [
                HierarchicalThemelet::class,
                PublishableThemelet::class,
                CompositeThemelet::class,
            ],
        );

        $this->themelet->setBaseThemelets(
            $this->getPluginThemelets($mybb)
        );


        /* @see AssetManagementTrait */
        $this->assetProperties = new SplObjectStorage();


        if ($this->optimization->getDirective('publication.all')) {
            $this->themelet->publishAssets();
        }
    }

    /**
     * @return ThemeletInterface[]
     */
    private function getPluginThemelets(MyBB $mybb): array
    {
        return array_map(
            fn (string $codename) => Plugin::get($codename)->getThemelet(),
            $mybb->cache?->read('plugins')['active'] ?? [],
        );
    }
}
