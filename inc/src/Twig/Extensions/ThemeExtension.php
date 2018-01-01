<?php

namespace MyBB\Twig\Extensions;

/**
 * A Twig extension class to provide functionality related to themes and assets.
 */
class ThemeExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    /**
     * @var \MyBB $mybb
     */
    private $mybb;

    /**
     * @var string $altRowState
     */
    private $altRowState;

    /**
     * Create a new instance of the ThemeExtension.
     *
     * @param \MyBB $mybb
     */
    public function __construct(\MyBB $mybb)
    {
        $this->mybb = $mybb;
        $this->altRowState = null;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('asset_url', [$this, 'getAssetUrl']),
            new \Twig_SimpleFunction('alt_trow', [$this, 'altTrow']),
        ];
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals()
    {
        return [
            'theme' => $GLOBALS['theme'],
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
    public function getAssetUrl(string $path, bool $useCdn = true) : string
    {
        // TODO: This could be smart and add cache busting query parameters to the path automatically...
        return $this->mybb->get_asset_url($path, $useCdn);
    }

    /**
     * Select an alternating row colour based on the previous call to this function.
     *
     * @param bool $reset Whether to reset the row state to `trow1`.
     * @return string `trow1` or `trow2` depending on the previous call.
     */
    public function altTrow(bool $reset = false)
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
    public function getStylesheets()
    {
        yield 1;
    }
}