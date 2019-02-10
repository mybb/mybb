<?php

namespace MyBB\Config;

use Illuminate\Config\Repository;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->app->singleton('config', function () {
            return new Repository();
        });
    }
}
