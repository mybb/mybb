<?php

namespace MyBB;

use Illuminate\Container\Container;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;

/**
 * Get an instance of a type fom the IoC container.
 *
 * @template T
 * @param class-string<T>|null $className The name of the type to resolve.
 * If this is null or an empty string, the container itself will be returned.
 * @param array $parameters An optional array of parameters to pass whilst resolving an instance.
 *
 * @return ($className is null ? \MyBB\Application : T)
 */
function app(?string $className = null, array $parameters = [])
{
    if (empty($className)) {
        return Container::getInstance();
    }

    return Container::getInstance()->make($className, $parameters);
}

/**
 * Register the given Twig extension with the Twig environment.
 *
 * @param string $className The full name of the extension class to register.
 * @param array $parameters Any parameters required to construct the given extension class.
 *
 * @return \Twig\Extension\ExtensionInterface The extension instance.
 */
function registerTwigExtension(string $className, array $parameters = []): ExtensionInterface
{
    /** @var \Twig\Environment $twig */
    $twig = app(Environment::class);

    if (!$twig->hasExtension($className)) {
        /** @var \Twig\Extension\ExtensionInterface $extension */
        $extension = app($className, $parameters);

        $twig->addExtension($extension);

        return $extension;
    }

    return $twig->getExtension($className);
}
