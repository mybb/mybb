<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use MyBB;
use MyBB\Extensions\Plugin;
use MyBB\Extensions\Theme;
use MyBB\View\Themelet\Decorator\CompositeThemelet;
use MyBB\View\Themelet\Decorator\Hierarchy\HierarchicalThemelet;
use MyBB\View\Themelet\Decorator\PublishableThemelet;
use MyBB\View\Themelet\Decorator\ThemeletDecorator;
use MyBB\View\Themelet\ThemeletInterface;

/**
 * Environment information and operations related to interface handling.
 */
class Runtime
{
    use AssetManagementTrait;
    use DataSharingTrait;
    use NamespacesTrait;

    public Theme $theme;

    public ThemeletInterface $themelet;

    public function __construct(MyBB $mybb, Theme $theme)
    {
        $this->theme = $theme;

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
