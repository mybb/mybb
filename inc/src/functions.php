<?php

namespace MyBB;

use Illuminate\Container\Container;
use Psr\Container\ContainerInterface;

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

/**
 * Render a view using the Twig template system.
 *
 * @param string $name The name of the template to render.
 * @param array $context An array of variables to be accessible within the template.
 *
 * @throws \Twig\Error\LoaderError  When the template cannot be found
 * @throws \Twig\Error\SyntaxError  When an error occurred during compilation
 * @throws \Twig\Error\RuntimeError When an error occurred during rendering
 *
 * @return string The rendered HTML content of the template.
 */
function template(string $name, array $context = [])
{
    /** @var \Twig\Environment $twig */
    $twig = app(\Twig\Environment::class);

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
function registerTwigExtension(string $className, array $parameters = []): \Twig\Extension\ExtensionInterface
{
    /** @var \Twig\Environment $twig */
    $twig = app(\Twig\Environment::class);

    if (!$twig->hasExtension($className)) {
        /** @var \Twig\Extension\ExtensionInterface $extension */
        $extension = app($className, $parameters);

        $twig->addExtension($extension);

        return $extension;
    }

    return $twig->getExtension($className);
}
