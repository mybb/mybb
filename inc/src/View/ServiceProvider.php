<?php

declare(strict_types=1);

namespace MyBB\View;

use Illuminate\Contracts\Support\DeferrableProvider;
use MyBB\Extensions\Theme\Theme;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\View\Runtime\Runtime;
use MyBB\View\Runtime\SharedData;
use Psr\Container\ContainerInterface;

class ServiceProvider extends \Illuminate\Support\ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->singleton(Theme::class, function (ContainerInterface $container) {
            return $container->get(ThemeRepository::class)->get(DEFAULT_THEME_PACKAGE);
        });

        $this->app->singleton(SharedData::class);

        $this->app->singleton(Runtime::class);

        $this->app->instance(Optimization::class, Optimization::WATCH);
    }

    public function provides()
    {
        return [
            Optimization::class,
            Runtime::class,
            SharedData::class,
            Theme::class,
        ];
    }
}
