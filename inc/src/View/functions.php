<?php

declare(strict_types=1);

namespace MyBB\View;

use MyBB\Stopwatch\Stopwatch;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\StaticLocator;
use MyBB\View\Locator\ThemeletLocator;
use MyBB\View\Runtime\Runtime;
use Twig\Environment;

use function MyBB\app;

const DEFAULT_THEME_PACKAGE = 'core.base';

/**
 * Output an Asset HTML tag, or delegate appending it to the DOM to the application.
 *
 * @param Locator|string $locator The path to the Asset.
 * @param bool $static Whether `$locatorString` is a literal path (not managed by the Theme System).
 * @param ResourceType|string|null $type The Asset type identifier. Deduced from `$path` if not provided.
 * @param bool $local Whether the Asset HTML tag should be returned, rather than delegating the appending of it.
 *
 * @api
 */
function asset(
    Locator|string $locator,
    bool $static = false,
    ResourceType|string|null $type = null,
    array $attributes = [],
    bool $local = false,
): ?string
{
    $view = app(Runtime::class);

    if ($locator instanceof Locator) {
        $locatorObject = $locator;
    } else {
        if ($static) {
            $locatorObject = StaticLocator::fromString($locator);
        } else {
            $locatorObject = Locator::fromString(
                $locator,
                [
                    'type' => ThemeletLocator::COMPONENT_SET,
                    'namespace' => ThemeletLocator::COMPONENT_CONTEXT,
                ],
                [
                    // may differ from evoking template's namespace
                    'namespace' => $view->getMainNamespace(),
                ],
            );
        }
    }

    if (is_string($type)) {
        $typeObject = ResourceType::from($type);
    } else {
        $typeObject = $type;
    }

    if ($local) {
        $asset = $view->themelet->getPublishedAsset(
            locator: $locatorObject,
            type: $typeObject,
        );

        $asset->setCompositeProperties(
            $view->themelet->getCompositeAssetProperties($locatorObject)
        );
        $asset->setCompositeProperties([
            'attributes' => $attributes,
        ]);

        $asset->insertedToDom = true;

        return $asset->getHtml();
    } else {
        $view->attachAsset(
            locator: $locatorObject,
            properties: [
                'attributes' => $attributes,
            ],
            type: $typeObject,
        );

        return null;
    }
}

/**
 * Get the path to an asset using the CDN URL if configured.
 *
 * @param Locator|string $locator The path to the file.
 * @param bool $static Whether `$locatorString` is a literal path (not managed by the Theme System).
 * @param bool $useCdn Whether to use the configured CDN options.
 *
 * @return string The complete URL to the asset.
 *
 * @api
 */
function assetUrl(
    Locator|string $locator,
    bool $static = false,
    bool $useCdn = true
): string
{
    $view = app(Runtime::class);

    if ($locator instanceof Locator) {
        $locatorObject = $locator;
    } else {
        if ($static) {
            $locatorObject = StaticLocator::fromString($locator);
        } else {
            $locatorObject = Locator::fromString(
                $locator,
                [
                    'type' => ThemeletLocator::COMPONENT_SET,
                    'namespace' => ThemeletLocator::COMPONENT_CONTEXT,
                ],
                [
                    // may differ from evoking template's namespace
                    'namespace' => $view->getMainNamespace(),
                ],
            );
        }
    }

    $asset = $view->themelet->getPublishedAsset($locatorObject);

    return $asset->getUrl($useCdn);
}

/**
 * Passes data to Resources.
 *
 * @param array<string, scalar> $data
 *
 * @api
 */
function set(array $data): void
{
    app(Runtime::class)->setSharedData($data);
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
 *
 * @api
 */
function template(string $name, array $context = []): string
{
    $stopwatchPeriod = app(Stopwatch::class)->start($name, 'core.view.template');

    /** @var Environment $twig */
    $twig = app(Environment::class);

    $result = $twig->render($name, $context);

    $stopwatchPeriod->stop();

    return $result;
}
