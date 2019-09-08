<?php

declare(strict_types = 1);

namespace MyBB\Plugins;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(HookManager::class, function () {
            return new HookManager();
        });
    }

    public function provides()
    {
        return [
            HookManager::class,
        ];
    }
}
