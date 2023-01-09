<?php
/**
 * Functions used with processing HTTP requests, responses, and HTML rendering.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use function MyBB\app;

#region rendering & output
/**
 * Renders a view using the Twig template system with minimal integration with other components.
 */
function template(string $name, array $context = []): string
{
    $loader = new \Twig\Loader\FilesystemLoader([
        MYBB_ROOT . 'inc/views/',
    ]);

    $twig = new \Twig\Environment($loader);

    if (
        function_exists('\MyBB\Maintenance\developmentEnvironment') &&
        developmentEnvironment() === true
    ) {
        $twig->enableDebug();
        $twig->addExtension(new \Twig\Extension\DebugExtension());
    }

    $twig->addExtension(
        app()->make(\MyBB\Twig\Extensions\CoreExtension::class)
    );
    $twig->addExtension(
        app()->make(\MyBB\Twig\Extensions\LangExtension::class)
    );

    if (defined('IN_INSTALL') || defined('IN_ADMINCP')) {
        $context['relative_asset_path'] = '..';
    } else {
        $context['relative_asset_path'] = '.';
    }

    $response = $twig->render($name, $context);

    return $response;
}
#endregion
