<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use MyBB;
use MyBB\Extensions\Plugin\Repository as PluginRepository;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\Extensions\Theme\Theme;
use MyBB\View\Optimization;
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
