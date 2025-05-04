<?php

namespace MyBB;

use MyBB\Stopwatch\Stopwatch;
use MyBB\Utilities\BreadcrumbManager;
use MyBB\Utilities\ManagedValue\FilesystemNestedStore;
use MyBB\Utilities\ManagedValue\Repository as ManagedValueRepository;
use Psr\Container\ContainerInterface;

/** @property \MyBB\Application $app */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Registers bindings in the container.
     */
    public function register()
    {
        $this->app->singleton(\MyBB::class, function () {
            return $GLOBALS['mybb'];
        });

        $this->app->singleton(\DB_Base::class, function () {
            return $GLOBALS['db'];
        });

        $this->app->singleton(\pluginSystem::class, function () {
            return $GLOBALS['plugins'];
        });

        $this->app->singleton(\MyLanguage::class, function () {
            return $GLOBALS['lang'];
        });

        $this->app->singleton(BreadcrumbManager::class, function (ContainerInterface $container) {
            /** @var \MyBB $mybb */
            $mybb = $container[\MyBB::class];

            return new BreadcrumbManager(
                $mybb->settings['bbname'],
                $mybb->settings['bburl']
            );
        });

        $this->app->singleton(Stopwatch::class);

        $this->app->bind(
            ManagedValueRepository::class,
            function (ContainerInterface $container, array $params) {
                $path = [
                    MYBB_ROOT . 'cache',
                    ...$params['path'],
                ];

                return new ManagedValueRepository(
                    new FilesystemNestedStore($path),
                    $path,
                    $this->app->make(Stopwatch::class),
                );
            },
        );
    }
}
