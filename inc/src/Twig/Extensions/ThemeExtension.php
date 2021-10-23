<?php

namespace MyBB\Twig\Extensions;

use DB_Base;
use MyBB;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * A Twig extension class to provide functionality related to themes and assets.
 */
class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var \MyBB $mybb
     */
    private $mybb;

    /**
     * @var \DB_Base $db
     */
    private $db;

    /**
     * @var string $altRowState
     */
    private $altRowState;

    /**
     * Create a new instance of the ThemeExtension.
     *
     * @param \MyBB $mybb
     * @param \DB_Base $db
     */
    public function __construct(MyBB $mybb, DB_Base $db)
    {
        $this->mybb = $mybb;
        $this->db = $db;

        $this->altRowState = null;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('asset_url', [$this, 'getAssetUrl']),
            new TwigFunction('alt_trow', [$this, 'altTrow']),
            new TwigFunction('get_stylesheets', [$this, 'getStylesheets']),
        ];
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals(): array
    {
        return [
            'theme' => $GLOBALS['theme'],
            'headerMessages' => isset($GLOBALS['headerMessages']) ? $GLOBALS['headerMessages'] : [],
        ];
    }

    /**
     * Get the path to an asset using the CDN URL if configured.
     *
     * @param string $path The path to the file.
     * @param bool $useCdn Whether to use the configured CDN options.
     *
     * @return string The complete URL to the asset.
     */
    public function getAssetUrl(string $path, bool $useCdn = true): string
    {
        // TODO: This could be smart and add cache busting query parameters to the path automatically...
        return $this->mybb->get_asset_url($path, $useCdn);
    }

    /**
     * Select an alternating row colour based on the previous call to this function.
     *
     * @param bool $reset Whether to reset the row state to `trow1`.
     *
     * @return string `trow1` or `trow2` depending on the previous call.
     */
    public function altTrow(bool $reset = false) : string
    {
        if (is_null($this->altRowState) || $this->altRowState === 'trow2' || $reset) {
            $this->altRowState = 'trow1';
        } else {
            $this->altRowState = 'trow2';
        }

        return $this->altRowState;
    }

    /**
     * Get a list of all the stylesheets applicable for the current page.
     *
     * @return \Generator A generator object that yields each stylesheet, as a full URL.
     */
    public function getStylesheets() : \Generator
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
