<?php

namespace MyBB;

use MyBB\Utilities\BreadcrumbManager;
use MyBB\Utilities\ManagedValue\FilesystemNestedStore;
use MyBB\Utilities\ManagedValue\Repository as ManagedValueRepository;
use MyBB\Utilities\Stopwatch\Stopwatch;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

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

        $this->app->singleton(\datacache::class, function () {
            return $GLOBALS['cache'];
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

        $this->app->singleton(RouteCollection::class, function (): RouteCollection {
            $routeCollection = new RouteCollection();

            if (is_dir(MYBB_ROOT.'inc/src/Http/Routes/')) {
                $files = glob(MYBB_ROOT.'inc/src/Http/Routes/*.php');

                foreach ($files as $file) {
                    $route = require_once $file;

                    if ($route instanceof Route) {
                        $routeCollection->add(
                            pathinfo($file, PATHINFO_FILENAME),
                            $route
                        );
                    }
                }
            }

            return $routeCollection;
        });

        $this->app->singleton(RequestContext::class, function (ContainerInterface $container): RequestContext {
            $context = new RequestContext();

            $forumUrl = parse_url($container->get(\MyBB::class)->settings['bburl']);

            $context->setHost($forumUrl['host']);

            $context->setScheme($forumUrl['scheme']);

            $context->setBaseUrl($forumUrl['path'] ?? '');

            $context->fromRequest(
                Request::createFromGlobals()
            );

            return $context;
        });

        $this->app->singleton(UrlGenerator::class, function (ContainerInterface $container) {
            $context = $container->get(RequestContext::class);

            $context->setBaseUrl($container->get(\MyBB::class)->settings['bburl']);


            return new UrlGenerator(
                $container->get(RouteCollection::class),
                $context,
            );
        });

        $this->app->singleton(UrlMatcher::class, function (ContainerInterface $container) {
            $context = $container->get(RequestContext::class);

            $context->setBaseUrl($container->get(\MyBB::class)->settings['bburl']);

            return new UrlMatcher(
                $container->get(RouteCollection::class),
                $context
            );
        });
    }
}
