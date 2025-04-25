<?php

namespace MyBB\Twig;

use Illuminate\Contracts\Container\Container;
use MyBB;
use MyBB\Twig\Extensions\CoreExtension;
use MyBB\Twig\Extensions\LangExtension;
use MyBB\Twig\Extensions\ThemeExtension;
use MyBB\Twig\Extensions\UrlExtension;
use MyBB\Utilities\BreadcrumbManager;
use MyBB\View\Optimization;
use MyBB\View\Runtime\Runtime;
use MyLanguage;
use pluginSystem;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extension\ProfilerExtension;
use Twig\Loader\LoaderInterface;
use Twig\Profiler\Profile;

/** @property \MyBB\Application $app */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(CoreExtension::class, function (Container $container) {
            return new CoreExtension(
                $container->make(MyBB::class),
                $container->make(MyLanguage::class),
                $container->make(pluginSystem::class),
                $container->make(BreadcrumbManager::class)
            );
        });

        $this->app->singleton(ThemeExtension::class);

        $this->app->singleton(LangExtension::class, function (Container $container) {
            return new LangExtension(
                $container->make(MyLanguage::class)
            );
        });

        $this->app->singleton(UrlExtension::class, function () {
            return new UrlExtension();
        });

        $this->app->singleton(LoaderInterface::class, function (Container $container) {
            $view = $container->get(Runtime::class);

            return new ThemeletLoader($view->themelet, $view->getMainNamespace());
        });

        $this->app->singleton('twig.options', function (Container $container) {
            $mybb = $container->get(MyBB::class);

            return [
                'debug' => $mybb->dev_mode,
                'auto_reload' => $container->get(Optimization::class)->getDirective('twig.autoReload'),
                'cache' => getenv('testing')
                    ? false
                    : __DIR__ . '/../../../cache/views',
            ];
        });

        $this->app->singleton(Environment::class, function (Container $container) {
            $mybb = $container->get(MyBB::class);

            $env = new Environment(
                $container->make(LoaderInterface::class),
                $container->make('twig.options')
            );

            $env->addExtension($container->make(CoreExtension::class));
            $env->addExtension($container->make(ThemeExtension::class));
            $env->addExtension($container->make(LangExtension::class));
            $env->addExtension($container->make(UrlExtension::class));

            if ($mybb->dev_mode) {
                $env->addExtension($container->make(DebugExtension::class));
            }

            if ($mybb->debug_mode && ($mybb->usergroup['cancp'] || $mybb->dev_mode)) {
                $profile = new Profile();

                $container->instance('twig.profile', $profile);

                $env->addExtension(new ProfilerExtension($profile));
            }

            return $env;
        });
    }

    public function provides()
    {
        return [
            CoreExtension::class,
            ThemeExtension::class,
            LangExtension::class,
            UrlExtension::class,
            LoaderInterface::class,
            Environment::class,
        ];
    }
}
