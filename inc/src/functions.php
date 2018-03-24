<?php

namespace MyBB;

use Illuminate\Container\Container;
use Psr\Container\ContainerInterface;

/**
 * Get an instance of a type fom the IoC container.
 *
 * @param string $className The name of the type to resolve.
 * If this is null or an empty string, the container itself will be returned.
 * @param array $parameters An optional array of parameters to pass whilst resolving an instance.
 *
 * @return ContainerInterface|mixed
 */
function app($className = null, array $parameters = [])
{
    if (is_null($className) || empty($className)) {
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
 * @throws \Twig_Error_Loader  When the template cannot be found
 * @throws \Twig_Error_Syntax  When an error occurred during compilation
 * @throws \Twig_Error_Runtime When an error occurred during rendering
 *
 * @return string The rendered HTML content of the template.
 */
function template(string $name, array $context = [])
{
    /** @var \Twig_Environment $twig */
    $twig = app(\Twig_Environment::class);

    return $twig->render($name, $context);
}
