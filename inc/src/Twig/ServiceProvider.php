<?php

namespace MyBB\Twig;

use DB_Base;
use Illuminate\Contracts\Container\Container;
use MyBB;
use MyBB\Twig\Extensions\CoreExtension;
use MyBB\Twig\Extensions\LangExtension;
use MyBB\Twig\Extensions\ThemeExtension;
use MyBB\Twig\Extensions\UrlExtension;
use MyBB\Utilities\BreadcrumbManager;
use MyLanguage;
use pluginSystem;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

/** @property \MyBB\Application $app */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->bind(CoreExtension::class, function (Container $container) {
            return new CoreExtension(
                $container->make(MyBB::class),
                $container->make(MyLanguage::class),
                $container->make(pluginSystem::class),
                $container->make(BreadcrumbManager::class)
            );
        });

        $this->app->bind(ThemeExtension::class, function (Container $container) {
            return new ThemeExtension(
                $container->make(MyBB::class),
                $container->make(DB_Base::class)
            ) ;
        });

        $this->app->bind(LangExtension::class, function (Container $container) {
            return new LangExtension(
                $container->make(MyLanguage::class)
            );
        });

        $this->app->bind(UrlExtension::class, function () {
            return new UrlExtension();
        });

        $this->app->bind(LoaderInterface::class, function () {
            $loader = new FilesystemLoader();

            $themeName = 'core.default'; // TODO
            $themePath = __DIR__ . '/../../themes/' . $themeName . '/';
            $namespaceDirectories = [
                'frontend',
                'parser',
            ];

            $mainNamespace = 'frontend';

            foreach ($namespaceDirectories as $namespaceDirectory) {
                if ($namespaceDirectory === $mainNamespace) {
                    $targetNamespace = FilesystemLoader::MAIN_NAMESPACE;
                } else {
                    $targetNamespace = $namespaceDirectory;
                }

                $path = $themePath . $namespaceDirectory . '/templates';

                $loader->addPath($path, $targetNamespace);
            }

            return $loader;
        });

        $this->app->bind('twig.options', function () {
            return [
                'debug' => true, // TODO: In live environments this should be false
                'cache' => __DIR__ . '/../../../cache/views',
            ];
        });

        $this->app->bind(Environment::class, function (Container $container) {
            $env = new Environment(
                $container->make(LoaderInterface::class),
                $container->make('twig.options')
            );

            $env->addExtension($container->make(CoreExtension::class));
            $env->addExtension($container->make(ThemeExtension::class));
            $env->addExtension($container->make(LangExtension::class));
            $env->addExtension($container->make(UrlExtension::class));

            // TODO: this shouldn't be registered in live environments
            $env->addExtension(new DebugExtension());

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
        ];
    }
}
