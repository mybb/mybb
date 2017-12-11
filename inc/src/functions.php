<?php

namespace MyBB;

use Illuminate\Container\Container;
use Psr\Container\ContainerInterface;

/**
 * Get an instance of a type fom the IoC container.
 *
 * @param string $className The name of the type to resolve. If this is null or an empty string, the container itself will be returned.
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
