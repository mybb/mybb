<?php

namespace MyBB\Twig\Extensions;

use DB_Base;
use MyBB;
use MyBB\View\ResourceType;
use MyBB\View\Runtime\Runtime;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

use function MyBB\View\asset;
use function MyBB\View\assetUrl;

/**
 * A Twig extension class to provide functionality related to themes and assets.
 */
class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var string $altRowState
     */
    private ?string $altRowState = null;

    /**
     * Create a new instance of the ThemeExtension.
     */
    public function __construct(
        private readonly MyBB $mybb,
        private readonly DB_Base $db,
        private readonly Runtime $view
    )
    {}

    public function getFunctions()
    {
        return [
            new TwigFunction('asset', [$this, 'getAsset']),
            new TwigFunction('asset_url', [$this, 'getAssetUrl']),
            new TwigFunction('alt_trow', [$this, 'altTrow']),
            new TwigFunction('attached_assets', [$this, 'getAttachedAssets']),
        ];
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals(): array
    {
        return array_merge(
            [
                'theme' => $GLOBALS['theme'],
                'headerMessages' => isset($GLOBALS['headerMessages']) ? $GLOBALS['headerMessages'] : [],
            ],
            $this->view->getSharedData(),
        );
    }

    /**
     * Output an Asset HTML tag, or delegate appending it to the DOM to the application.
     *
     * @param string $locator The path to the Asset.
     * @param bool $static Whether `$locatorString` is a literal path (not managed by the Theme System).
     * @param ?string $type The Asset type identifier. Deduced from `$path` if not provided.
     * @param bool $local Whether the Asset HTML tag should be returned, rather than delegating the appending of it.
     *
     * @api
     */
    public function getAsset(
        string $locator,
        bool $static = false,
        ?string $type = null,
        array $attributes = [],
        bool $local = false,
    ): ?string
    {
        return asset(...func_get_args());
    }

    /**
     * Get the path to an asset using the CDN URL if configured.
     *
     * @param string $locator The path to the file.
     * @param bool $static Whether `$locatorString` is a literal path (not managed by the Theme System).
     * @param bool $useCdn Whether to use the configured CDN options.
     *
     * @return string The complete URL to the asset.
     *
     * @api
     */
    public function getAssetUrl(string $locator, bool $static = false, bool $useCdn = true): string
    {
        return assetUrl(...func_get_args());
    }

    /**
     * Select an alternating row colour based on the previous call to this function.
     *
     * @param bool $reset Whether to reset the row state to `trow1`.
     *
     * @return string `trow1` or `trow2` depending on the previous call.
     * @deprecated Use CSS pseudo-classes instead.
     */
    public function altTrow(bool $reset = false): string
    {
        if (is_null($this->altRowState) || $this->altRowState === 'trow2' || $reset) {
            $this->altRowState = 'trow1';
        } else {
            $this->altRowState = 'trow2';
        }

        return $this->altRowState;
    }

    /**
     * Get assets attached to the current page.
     *
     * @param bool $inserting Get assets not yet inserted, and declare them as such.
     *
     * @api
     */
    public function getAttachedAssets(string $type, bool $inserting = false): array
    {
        return $this->view->getAttachedAssets(
            ResourceType::from($type),
            $inserting,
        );
    }

    /**
     * Get a list of stylesheets applicable for the current page in the MyBB <= 1.8 format.
     *
     * @return \Generator A generator object that yields each stylesheet, as a full URL.
     */
    private function getLegacyStyles(): \Generator
    {
        // TODO: Optimise this function - it looks like it can be improved at a glance
        $theme = $GLOBALS['theme'];

        $alreadyLoaded = [];

        if (!is_array($theme['stylesheets'])) {
            $theme['stylesheets'] = my_unserialize($theme['stylesheets']);
        }

        $stylesheetScripts = array("global", basename($_SERVER['PHP_SELF']));
        if (!empty($theme['color'])) {
            $stylesheetScripts[] = $theme['color'];
        }

        $stylesheetActions = array("global");
        if (!empty($this->mybb->input['action'])) {
            $stylesheetActions[] = $this->mybb->get_input('action');
        }
        foreach ($stylesheetScripts as $stylesheetScript) {
            // Load stylesheets for global actions and the current action
            foreach ($stylesheetActions as $stylesheet_action) {
                if (!$stylesheet_action) {
                    continue;
                }

                if (!empty($theme['stylesheets'][$stylesheetScript][$stylesheet_action])) {
                    // Actually add the stylesheets to the list
                    foreach ($theme['stylesheets'][$stylesheetScript][$stylesheet_action] as $pageStylesheet) {
                        if (!empty($alreadyLoaded[$pageStylesheet])) {
                            continue;
                        }

                        if (strpos($pageStylesheet, 'css.php') !== false) {
                            $stylesheetUrl = $this->mybb->settings['bburl'] . '/' . $pageStylesheet;
                        } else {
                            $stylesheetUrl = $this->mybb->get_asset_url($pageStylesheet);
                        }

                        if ($this->mybb->settings['minifycss']) {
                            $stylesheetUrl = str_replace('.css', '.min.css', $stylesheetUrl);
                        }

                        if (strpos($pageStylesheet, 'css.php') !== false) {
                            // We need some modification to get it working with the displayorder
                            $queryString = parse_url($stylesheetUrl, PHP_URL_QUERY);
                            $id = (int)my_substr($queryString, 11);
                            $query = $this->db->simple_select("themestylesheets", "name", "sid={$id}");
                            $realName = $this->db->fetch_field($query, "name");
                            $themeStylesheets[$realName] = $stylesheetUrl;
                        } else {
                            $themeStylesheets[basename($pageStylesheet)] = $stylesheetUrl;
                        }

                        $alreadyLoaded[$pageStylesheet] = 1;
                    }
                }
            }
        }
        unset($actions);

        if (!empty($themeStylesheets) && is_array($theme['disporder'])) {
            foreach ($theme['disporder'] as $style_name => $order) {
                if (!empty($themeStylesheets[$style_name])) {
                    yield $themeStylesheets[$style_name];
                }
            }
        }
    }
}
