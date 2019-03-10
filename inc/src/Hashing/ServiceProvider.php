<?php

declare(strict_types = 1);

namespace MyBB\Hashing;

class ServiceProvider extends \Illuminate\Hashing\HashServiceProvider
{
    public function register()
    {
        $this->app->singleton('hash', function ($app) {
            return new HashManager($app);
        });

        $this->app->singleton('hash.driver', function ($app) {
            /** @var \MyBB\Hashing\HashManager $manager */
            $manager = $app['hash'];

            return $manager->driver();
        });
    }
}
