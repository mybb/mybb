<?php

namespace MyBB\Twig\Extensions;

use DB_Base;
use MyBB;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;
use Twig\Node\Node;
use Twig\Node\ModuleNode;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Compiler;

class MyBBTwigDispNode extends Node
{
    public function compile(\Twig\Compiler $compiler): void
    {
        $compiler
            ->write("\$this->env->getExtension('".ThemeExtension::class."')->onDisplayMyBBTwigTemplate(\$this, \$context);\n")
        ;
    }
}

class MyBBTwigNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $node->setNode('display_start', new MyBBTwigDispNode);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}

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

    public function getNodeVisitors(): array
    {
        return [new MyBBTwigNodeVisitor(static::class)];
    }

    public function onDisplayMyBBTwigTemplate($node, &$context)
    {
        global $plugins, $twig_templates;

        if (empty($twig_templates)) {
            $twig_templates = [];
        }
        $twig_templates[] = $node->getTemplateName();

        $params = ['name' => $node->getTemplateName(), 'context' => &$context];
        $plugins->run_hooks('template', $params);
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
        global $theme, $cache, $mybb;

        $stylesheets_a = [];

        require_once MYBB_ROOT.'inc/functions_themes.php';
        $color = !empty($theme['color']) ? $theme['color'] : '';
        $stylesheets_a[''] = get_themelet_stylesheets($theme['codename'], $color, false, false);
        foreach ($cache->read('plugins')['active'] as $plugin_code) {
            $stylesheets_a[$plugin_code] = get_themelet_stylesheets($plugin_code, $color, false, true);
        }

        $stylesheetScripts = array("global", basename($_SERVER['PHP_SELF']));
        if (!empty($color)) {
            $stylesheetScripts[] = $color;
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

                foreach ($stylesheets_a as $codename => $stylesheets) {
                    if (!empty($stylesheets[$stylesheetScript][$stylesheet_action])) {
                        // Actually add the stylesheets to the list
                        foreach ($stylesheets[$stylesheetScript][$stylesheet_action] as $pageStylesheet) {
                            $minify = !empty($mybb->settings['minifycss']);
                            list($plugin_code, $namespace, $component, $filename) = parse_res_spec1($pageStylesheet);
                            if ($minify && my_strtolower(substr($filename, -8)) !== '.min.css') {
                                $filename = substr($filename, 0, -3).'min.css';
                            }
                            if (empty($plugin_code)) {
                                $res_spec = "~ct~{$namespace}:{$component}:{$filename}"; // Current theme stylesheet
                            } else {
                                $res_spec = "~cp~{$plugin_code}:{$component}:{$filename}"; // Plugin stylesheet for current theme
                            }
                            if (!empty($themeStylesheets[$res_spec])) {
                                continue;
                            }
                            $stylesheetUrl = $this->mybb->get_asset_url($res_spec);
                            $themeStylesheets[$res_spec] = $stylesheetUrl;
                        }
                    }
                }
            }
        }

        // Return the stylesheet paths we've found via yielding, honouring their display order.
        if (!empty($themeStylesheets) && isset($theme['disporder']) && is_array($theme['disporder'])) {
            foreach ($theme['disporder'] as $style_name => $order) {
                if (!empty($themeStylesheets[$style_name])) {
                    $style_path = $themeStylesheets[$style_name];
                    unset($themeStylesheets[$style_name]);
                    yield $style_path;
                }
            }
        }
        // Now for those without a display order. Mostly (solely?), these will be plugin stylesheets.
        foreach ($themeStylesheets as $style_path) {
           yield $style_path;
        }
    }
}
