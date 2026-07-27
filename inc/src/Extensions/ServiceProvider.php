<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use Illuminate\Contracts\Container\Container;
use MyBB\Extensions\Plugin\Plugin;
use MyBB\Extensions\Plugin\Repository as PluginRepository;
use MyBB\Extensions\Theme\Repository as ThemeRepository;
use MyBB\Extensions\Theme\Theme;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * @var array<class-string<Extension>, class-string<Repository>>
     */
    private const EXTENSION_REPOSITORY_CLASSES = [
        Plugin::class => PluginRepository::class,
        Theme::class => ThemeRepository::class,
    ];

    public function register(): void
    {
        foreach (self::EXTENSION_REPOSITORY_CLASSES as $class) {
            $this->app->singleton($class);
        }

        $this->app->singleton(RepositoryRegistry::class, function (Container $container) {
            return new RepositoryRegistry(
                array_map(
                    fn (string $class) => $container->get($class),
                    self::EXTENSION_REPOSITORY_CLASSES,
                )
            );
        });
    }

    public function provides(): array
    {
        return self::EXTENSION_REPOSITORY_CLASSES + [
            RepositoryRegistry::class,
        ];
    }
}
