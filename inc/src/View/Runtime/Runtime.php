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

/**
 * Environment information and operations related to interface handling.
 */
class Runtime
{
    use NamespacesTrait;

    public readonly ViewletInterface $viewlet;
    public readonly AssetManager $assetManager;

    private readonly array $globalThemeArray;

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

        $this->assetManager = new AssetManager($this->viewlet);

        if ($this->optimization->getDirective('publication.all')) {
            $this->viewlet->publishAssets();
        }
    }

    /**
     * Sets the environment information used in interface handling.
     */
    public function setContext(array $context): void
    {
        $this->assetManager->setContext($context);
    }

    /**
     * Returns an array with legacy properties assigned to the global `$theme` variable.
     */
    public function getGlobalThemeArray(): array
    {
        if (!isset($this->globalThemeArray)) {
            $theme = $this->themeModel->toArray();

            $theme = array_merge($theme, $this->themeModel->getResolvedProperties());

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

            $this->globalThemeArray = $theme;
        }

        return $this->globalThemeArray;
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
