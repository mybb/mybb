<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use MyBB;
use MyBB\Database\Models\Theme as ThemeModel;
use MyBB\Extensions\Plugin\Repository as PluginRepository;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\Extensions\Theme\Theme;
use MyBB\View\Optimization;
use MyBB\View\ResourceType;
use MyBB\View\Viewlet\Decorator\CompositeViewlet;
use MyBB\View\Viewlet\Decorator\Hierarchy\HierarchicalViewlet;
use MyBB\View\Viewlet\Decorator\PublishableViewlet;
use MyBB\View\Viewlet\Decorator\ViewletDecorator;
use MyBB\View\Viewlet\ViewletInterface;
use SplObjectStorage;

/**
 * Environment information and operations related to interface handling.
 */
class Runtime
{
    use AssetManagementTrait;
    use NamespacesTrait;

    public readonly ViewletInterface $viewlet;

    public function __construct(
        private readonly MyBB $mybb,
        private readonly ThemeModel $themeModel,
        private readonly Theme $theme,
        private readonly ThemeRepository $themeRepository,
        private readonly PluginRepository $pluginRepository,
        private readonly Optimization $optimization,
    )
    {
        $this->viewlet = $this->getDecoratedViewlet();

        /* @see AssetManagementTrait */
        $this->assetProperties = new SplObjectStorage();

        if ($this->optimization->getDirective('publication.all')) {
            $this->viewlet->publishAssets();
        }
    }

    /**
     * Returns an array with legacy properties assigned to the global `$theme` variable.
     */
    public function getGlobalThemeArray(): array
    {
        $theme = $this->themeModel->toArray();

        $theme = array_merge($theme, (array)my_unserialize($theme['properties']));

        $theme['imgdir'] = $this->viewlet->getPublishingPath('frontend', ResourceType::IMAGE);

        if (!is_dir(MYBB_ROOT . $theme['imgdir'])) {
            $theme['imgdir'] = 'images';
        }

        $theme['imglangdir'] = $theme['imgdir'];
        $theme['logo'] = 'images/logo.png';
        $theme['tablespace'] = '5';
        $theme['borderwidth'] = '0';

        // If a language directory for the current language exists within the theme - we use it
        foreach (
            [
                $this->mybb->user['language'] ?? '',
                $this->mybb->settings['bblanguage'],
            ] as $path
        ) {
            if (!empty($path)) {
                $path = $theme['imgdir'] . '/' . $path;

                if (is_dir(MYBB_ROOT . $path)) {
                    $theme['imglangdir'] = $path;

                    break;
                }
            }
        }

        $theme['imgdir'] = $this->mybb->get_asset_url($theme['imgdir']);
        $theme['imglangdir'] = $this->mybb->get_asset_url($theme['imglangdir']);
        $theme['logo'] = $this->mybb->get_asset_url($theme['logo']);

        // TODO initialize theme properties from package & load set values from DB
        $theme['editortheme'] = 'mybb.css';

        return $theme;
    }

    private function getDecoratedViewlet(): ViewletInterface
    {
        $viewlet = $this->theme->getViewlet();

        // HierarchicalViewlet
        $viewlet = new HierarchicalViewlet(
            $viewlet,
            $this->themeRepository,
            $this->optimization,
        );

        $pluginViewlets = $this->getPluginViewlets();

        $viewlet->setBaseViewlets($pluginViewlets);


        // PublishableViewlet, CompositeViewlet

        $viewlet = ViewletDecorator::decorate(
            $viewlet,
            [
                PublishableViewlet::class,
                CompositeViewlet::class,
            ],
        );

        foreach ($pluginViewlets as $pluginViewlet) {
            foreach ($pluginViewlet->getNamespaces() as $namespace) {
                $viewlet->applyNamespace($namespace);
            }
        }


        return $viewlet;
    }

    /**
     * @return ViewletInterface[]
     */
    private function getPluginViewlets(): array
    {
        return array_map(
            fn (string $codename) => $this->pluginRepository->get($codename)->getViewlet(),
            $this->mybb->cache?->read('plugins')['active'] ?? [],
        );
    }
}
