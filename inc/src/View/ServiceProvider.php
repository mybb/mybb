<?php

declare(strict_types=1);

namespace MyBB\View;

use Illuminate\Contracts\Support\DeferrableProvider;
use MyBB\Extensions\Theme;
use MyBB\View\Runtime\Runtime;
use Twig\Environment;

class ServiceProvider extends \Illuminate\Support\ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->singleton(Theme::class, function () {
            return Theme::get(DEFAULT_THEME_PACKAGE);
        });

        $this->app->singleton(Runtime::class);

        $this->app->instance(Optimization::class, Optimization::WATCH);

        $this->app->afterResolving(
            Environment::class,
            fn ($twig) => $this->app->get(Runtime::class)->setTwig($twig),
        );
    }

    public function provides()
    {
        return [
            Optimization::class,
            Runtime::class,
            Theme::class,
        ];
    }
}
