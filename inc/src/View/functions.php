<?php

declare(strict_types=1);

namespace MyBB\View;

use MyBB\Stopwatch\Stopwatch;
use MyBB\View\Asset\Asset;
use MyBB\View\Runtime\Runtime;
use Twig\Environment;

use function MyBB\app;

const DEFAULT_THEME_PACKAGE = 'core.base';

/**
 * Passes data to Resources.
 *
 * @param array<string, scalar> $data
 */
function set(array $data): void
{
    app(Runtime::class)->setSharedData($data);
}

function directive(string $name): mixed
{
    return app(Optimization::class)->getDirective($name);
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
 * Replaces placeholders with asset tags yet to be inserted into DOM.
 * Used for assets declared after the placeholder's template was rendered.
 */
function insertDeferredAttachedAssets(string $contents): string
{
    foreach (Runtime::ATTACHABLE_TYPES as $type) {
        $assets = app(Runtime::class)->getAttachedAssets($type, inserting: true);

        $tags = array_map(
            fn (Asset $asset) => template(
                'partials/' . $type->value . '.twig',
                [
                    'asset' => $asset,
                ],
            ),
            $assets,
        );

        $html = implode($tags);

        $contents = str_replace(
            '<!-- deferred_attached_assets.' . $type->value . ' -->',
            $html,
            $contents,
        );
    }

    return $contents;
}
