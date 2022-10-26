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
     * @var array $twigTemplateContexts
     */
    private $twigTemplateContexts;

    /**
     * @var array $attachedJsFilesAttrs
     */
    private $attachedJsFilesAttrs = [];

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
        global $plugins;

        $tpl = $node->getTemplateName();
        if (empty($this->twigTemplateContexts[$tpl])) {
            $this->twigTemplateContexts[$tpl] = [];
        }
        $this->twigTemplateContexts[$tpl][] = $context;

        $params = ['name' => $tpl, 'context' => &$context];
        $plugins->run_hooks('template', $params);
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('asset_url', [$this, 'getAssetUrl']),
            new TwigFunction('alt_trow', [$this, 'altTrow']),
            new TwigFunction('get_stylesheets', [$this, 'getStylesheets']),
            new TwigFunction('get_jscripts', [$this, 'getJscripts'], ['needs_context' => true]),
            new TwigFunction('attach_resource', [$this, 'attachResource']),
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

        $themeStylesheets = $stylesheets_a = [];

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

    public function checkConditions($conditions, $template)
    {
        if (!array_key_exists($template, $this->twigTemplateContexts)) {
            return false;
        }
        $conditions_met = false;
        foreach ($this->twigTemplateContexts[$template] as $context) {
            $conditions_met = true;
            foreach ((array)$conditions as $key => $value) {
                if (is_int($key)) {
                    $item = $value;
                    $test_val = true;
                } else {
                    $item = $key;
                    $test_val = $value;
                }
                $item_is_missing = false;
                $curr = $context;
                $a = explode('.', $item);
                foreach ($a as $i => $k) {
                    if (is_object($curr) && !property_exists($curr, $k)
                        ||
                        !is_object($curr) && !isset($curr[$k])
                    ) {
                        $item_is_missing = true;
                        break;
                    } else {
                        $curr = is_object($curr) ? $curr->$k : $curr[$k];
                    }
                }
                if ($item_is_missing || $curr != $test_val) {
                    $conditions_met = false;
                    break;
                }
            }
            if ($conditions_met) {
                break;
            }
        }

        // Earlier return possible
        return $conditions_met;
    }

    /**
     * Get a list of all the Javascript files applicable to the current page.
     *
     * @return \Generator A generator object that yields each file, as an array of:
     *                    'path'       => String. The full URL.
     *                    'attributes' => String. Attributes for the <script> tag.
     */
    public function getJscripts($context): \Generator
    {
        global $mybb, $theme;

        require_once MYBB_ROOT.'inc/functions_themes.php';
        $jscripts = get_theme_jscripts($theme['codename']);

        // Most negative is highest priority (in terms of dependency).
        $file_priorities = [];

        if (!empty($jscripts)) {
            foreach ($jscripts as $scriptname => $scriptdata) {
                $priority = -1;
                $has_been_attached = isset($this->attachedJsFilesAttrs[$scriptname]);
                if (!isset($scriptdata['attached_to'])) {
                    $scriptdata['attached_to'] = [['' => '']];
                } else if (empty($scriptdata['attached_to'])) {
                    $scriptdata['attached_to'] = [['script' => 'global']];
                }
                foreach ($scriptdata['attached_to'] as $attached_to) {
                    $is_global = isset($attached_to['script']) && empty($attached_to['script']) && isset($attached_to['template']) && empty($attached_to['template']);
                    $have_script = !empty($attached_to['script']);
                    $script_matches = $have_script && ($attached_to['script'] == 'global' || $attached_to['script'] == basename($_SERVER['PHP_SELF']));
                    $have_template = !empty($attached_to['template']);
                    $template_matches = $have_template && in_array($attached_to['template'], array_keys($this->twigTemplateContexts));
                    if ($has_been_attached
                        ||
                        (
                         ($is_global
                          ||
                          ($have_script && $script_matches && (!$have_template || $template_matches))
                          ||
                          (!$have_script && $have_template && $template_matches)
                         )
                         &&
                         (empty($attached_to['ext'])
                          ||
                          $attached_to['ext'] == $mybb->get_input('ext')
                         )
                         &&
                         (empty($attached_to['actions'])
                          ||
                          in_array('global', $attached_to['actions'])
                          ||
                          in_array($mybb->get_input('action'), $attached_to['actions'])
                         )
                         &&
                         (!$have_template
                          ||
                          empty($attached_to['conditional_on'])
                          ||
                          $this->checkConditions((array)$attached_to['conditional_on'], $attached_to['template'])
                         )
                        )
                    ) {
                        $attrs = empty($scriptdata['attributes']) ? [] : $scriptdata['attributes'];
                        if (empty($this->attachedJsFilesAttrs[$scriptname])) {
                            $this->attachedJsFilesAttrs[$scriptname] = $attrs;
                        } else {
                            $this->attachedJsFilesAttrs[$scriptname] = array_merge($this->attachedJsFilesAttrs[$scriptname], $attrs);
                        }

                        if (!array_key_exists($scriptname, $file_priorities)) {
                            $file_priorities[$scriptname] = ['specifier' => $scriptdata['specifier'], 'priorities' => []];
                        }
                        $file_priorities[$scriptname]['priorities'][] = $priority;

                        $depends_on = [];
                        if (!empty($scriptdata['depends_on'])) {
                            $depends_on = $scriptdata['depends_on'];
                        }
                        do {
                            $parents = [];
                            $priority -= 1;
                            foreach ($depends_on as $dependable) {
                                if (!array_key_exists($dependable, $file_priorities)) {
                                    $file_priorities[$dependable] = ['specifier' => $dependable, 'priorities' => []];
                                }
                                $file_priorities[$dependable]['priorities'][] = $priority;
                                $dep_deps = empty($jscripts[$dependable]['depends_on'])
                                                ? []
                                                : $jscripts[$dependable]['depends_on'];
                                $parents = array_merge($parents, $dep_deps);
                            }
                            $depends_on = $parents;
                        } while (!empty($depends_on));
                        break;
                    }
                }
            }
        }

        $js_files = $js_files_pr = [];
        foreach ($file_priorities as $file_path => $priorities) {
            // Find the highest priority (most negative value) for the script (in terms of dependency).
            $priority = min($priorities['priorities']);
            if (empty($js_files_pr[$priority])) {
                $js_files_pr[$priority] = [];
            }
            $js_files_pr[$priority][] = ['specifier' => $priorities['specifier'], 'file_path' => $file_path];
        }
        ksort($js_files_pr);
        foreach ($js_files_pr as $entries) {
            foreach ($entries as $entry) {
                $attributes = '';
                if (!empty($this->attachedJsFilesAttrs[$entry['file_path']])) {
                    foreach ($this->attachedJsFilesAttrs[$entry['file_path']] as $attr => $val) {
                        $attributes .= ' '.htmlspecialchars_uni($attr).'="'.htmlspecialchars_uni($val).'"';
                    }
                }
                $js_files[] = [
                    'path' => $mybb->get_asset_url($entry['specifier']),
                    'attributes' => $attributes
                ];
            }
        }

        foreach ($js_files as $entry) {
            yield $entry;
        }
    }

    public function attachResource($resource, $attrs = [])
    {
        if (my_strtolower(get_extension($resource)) !== 'js') {
            error('`attach_resource()` was called in a Twig template for a resource other than a Javascript file ('.htmlspecialchars_uni($resource).'). This is not yet supported.');
        }

        if (my_substr($resource, 0, 1) == '@') {
            $resource = normalise_res_spec1($resource);
        }

        if (empty($this->attachedJsFilesAttrs[$resource])) {
            $this->attachedJsFilesAttrs[$resource] = $attrs;
        } else {
            $this->attachedJsFilesAttrs[$resource] = array_merge($this->attachedJsFilesAttrs[$resource], $attrs);
        }
    }
}
