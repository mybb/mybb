<?php

namespace MyBB;

use Illuminate\Container\Container;
use MyBB\Stopwatch\Stopwatch;
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
    $stopwatchPeriod = app(Stopwatch::class)->start($name, 'core.view.template');

    /** @var Environment $twig */
    $twig = app(Environment::class);

    $result = $twig->render($name, $context);

    $stopwatchPeriod->stop();

    return $result;
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
