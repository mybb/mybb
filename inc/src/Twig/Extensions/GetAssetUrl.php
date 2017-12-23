<?php

namespace MyBB\Twig\Extensions;

/**
 * A Twig extension to generate URLs to static assets, using the CDN and board URL settings defined in settings.
 */
class GetAssetUrl extends \Twig_Extension
{
    /**
     * @var \MyBB $mybb
     */
    private $mybb;

    /**
     * Create a new instance of the GetAssetUrl extension.
     *
     * @param \MyBB $mybb
     */
    public function __construct(\MyBB $mybb)
    {
        $this->mybb = $mybb;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('asset_url', [$this, 'getAssetUrl']),
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
}