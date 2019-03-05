<?php
declare(strict_types = 1);

namespace MyBB;

class PluginSystem
{
    /**
     * The hooks to which plugins can be attached.
     *
     * @var array
     */
    protected $hooks;

    /**
     * The current hook which we're in (if any)
     *
     * @var string
     */
    protected $currentHook;

    /**
     * Create a new plugin system instance.
     */
    public function __construct()
    {
        $this->hooks = [];
        $this->currentHook = '';
    }

    /**
     * @return array
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    /**
     * @return string
     */
    public function getCurrentHook(): string
    {
        return $this->currentHook;
    }

    /**
     * Load all plugins.
     */
    public function load()
    {
        global $cache, $plugins;

        $pluginList = $cache->read("plugins");
        if (!empty($pluginList['active']) && is_array($pluginList['active'])) {
            foreach ($pluginList['active'] as $plugin) {
                if (!empty($plugin) && file_exists(MYBB_ROOT . "inc/plugins/{$plugin}.php")) {
                    require_once MYBB_ROOT . "inc/plugins/{$plugin}.php";
                }
            }
        }
    }

    /**
     * Add a hook onto which a plugin can be attached.
     *
     * @param string $hook The hook name.
     * @param callable $function The function of this hook.
     * @param int $priority The priority this hook has.
     * @param string|null $file The optional file belonging to this hook.
     *
     * @return boolean Whether the hook was added.
     */
    public function addHook(string $hook, callable $function, int $priority = 10, ?string $file = ""): bool
    {
        $methodRepresentation = $this->getStringRepresentationForCallable($function);

        // Check to see if we already have this hook running at this priority
        if (!empty($this->hooks[$hook][$priority][$methodRepresentation]) &&
            is_array($this->hooks[$hook][$priority][$methodRepresentation])) {
            return true;
        }

        // Add the hook
        $this->hooks[$hook][$priority][$methodRepresentation] = array(
            'function' => $function,
            'file' => $file,
        );

        return true;
    }

    /**
     * Get the string representation for the given callable.
     *
     * @param callable $function The function to get the string representation for.
     *
     * @return string The string representation of the callable array.
     */
    private function getStringRepresentationForCallable(callable $function): string
    {
        if (is_array($function)) {
            // Class function

            if (is_string($function[0])) {
                // Static class method
                return sprintf('%s::%s', $function[0], $function[1]);
            }

            return sprintf('%s->%s', spl_object_hash($function[0]), $function[1]);
        } elseif (is_object($function) && $function instanceof \Closure) {
            // Closure

            return spl_object_hash($function);
        } elseif (is_string($function)) {
            // Function name string

            return $function;
        } else {
            $type = typeOf($function);

            throw new \InvalidArgumentException("Invalid function type: {$type}");
        }
    }

    /**
     * Run the hooks that have plugins.
     *
     * @param string $hook The name of the hook that is run.
     * @param mixed ...$arguments The arguments for the hook that is run. The passed value MUST be a variable.
     *
     * @return void
     */
    public function runHooks(string $hook, &...$arguments): void
    {
        if (!isset($this->hooks[$hook]) || !is_array($this->hooks[$hook])) {
            return;
        }

        $this->currentHook = $hook;

        try {
            ksort($this->hooks[$hook]);

            foreach ($this->hooks[$hook] as $priority => $hooks) {
                if (is_array($hooks)) {
                    foreach ($hooks as $key => $hook) {
                        if ($hook['file']) {
                            require_once $hook['file'];
                        }

                        $returnArgs = call_user_func_array($hook['function'], $arguments);

                        if ($returnArgs) {
                            $arguments = $returnArgs;
                        }
                    }
                }
            }
        } finally {
            $this->currentHook = '';
        }
    }

    /**
     * Remove a specific hook.
     *
     * @param string $hook The name of the hook.
     * @param callable $function The function of the hook.
     * @param int $priority The priority of the hook.
     *
     * @return bool Whether the hook was removed successfully.
     */
    public function removeHook(string $hook, callable $function, int $priority = 10): bool
    {
        $methodRepresentation = $this->getStringRepresentationForCallable($function);

        if (isset($this->hooks[$hook][$priority][$methodRepresentation])) {
            unset($this->hooks[$hook][$priority][$methodRepresentation]);
        }

        return true;
    }

    /**
     * Establishes if a particular plugin is compatible with this version of MyBB.
     *
     * @param string $plugin The name of the plugin.
     *
     * @return boolean TRUE if compatible, FALSE if incompatible.
     */
    public function isCompatible(string $plugin): bool
    {
        global $mybb;

        // Ignore potentially missing plugins.
        if (!file_exists(MYBB_ROOT . "inc/plugins/{$plugin}.php")) {
            return true;
        }

        require_once MYBB_ROOT . "inc/plugins/{$plugin}.php";

        $infoFunc = "{$plugin}_info";
        if (!function_exists($infoFunc)) {
            return false;
        }

        $pluginInfo = $infoFunc();

        // No compatibility set or compatibility = * - assume compatible
        if (!$pluginInfo['compatibility'] || $pluginInfo['compatibility'] == "*") {
            return true;
        }

        $compatibility = explode(",", $pluginInfo['compatibility']);
        foreach ($compatibility as $version) {
            $version = trim($version);
            $version = str_replace("*", ".+", preg_quote($version));
            $version = str_replace("\.+", ".+", $version);
            if (preg_match("#{$version}#i", $mybb->version_code)) {
                return true;
            }
        }

        // Nothing matches
        return false;
    }
}
