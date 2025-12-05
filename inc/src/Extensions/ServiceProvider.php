<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use MyBB\Extensions\Plugin\Repository as PluginRepository;
use MyBB\Extensions\Theme\Repository as ThemeRepository;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginRepository::class);
        $this->app->singleton(ThemeRepository::class);
    }

    public function provides(): array
    {
        return [
            PluginRepository::class,
            ThemeRepository::class,
        ];
    }
}
