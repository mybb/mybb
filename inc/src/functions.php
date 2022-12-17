<?php

namespace MyBB;

use Illuminate\Container\Container;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;

/**
 * Get an instance of a type fom the IoC container.
 *
 * @param string|null $className The name of the type to resolve.
 * If this is null or an empty string, the container itself will be returned.
 * @param array $parameters An optional array of parameters to pass whilst resolving an instance.
 *
 * @return \MyBB\Application|mixed
 */
function app(?string $className = null, array $parameters = [])
{
    if (empty($className)) {
        return Container::getInstance();
    }

    return Container::getInstance()->make($className, $parameters);
}

function strip_frontend_ns($name)
{
    static $strip = '@frontend/';
    static $strip_len;
    if (!$strip_len) $strip_len = strlen($strip);
    if (my_substr($name, 0, $strip_len) === $strip) {
        $name = my_substr($name, $strip_len);
    }
    return $name;
}

/**
 * Render a view using the Twig template system.
 *
 * @param string $name The name of the template to render.
 * @param array $context An array of variables to be accessible within the template.
 *
 * @return string The rendered HTML content of the template.
 *
 * @throws \Twig\Error\LoaderError
 * @throws \Twig\Error\RuntimeError
 * @throws \Twig\Error\SyntaxError
 */
function template(string $name, array $context = [])
{
    /** @var Environment $twig */
    $twig = app(Environment::class);

    // Strip explicit default namespace (frontend) because we don't register it with the loader.
    // Instead, we register it as the main namespace (i.e., when $name doesn't begin with an @).
    $name = strip_frontend_ns($name);

    return $twig->render($name, $context);
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
