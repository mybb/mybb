<?php

declare(strict_types = 1);

namespace MyBB\Plugins;

class HookManager
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
     * @var string|null
     */
    protected $currentHook;

    /**
     * Create a new plugin system instance.
     */
    public function __construct()
    {
        $this->hooks = [];
        $this->currentHook = null;
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
    public function getCurrentHook(): ?string
    {
        return $this->currentHook;
    }

    /**
     * Add a hook onto which a plugin can be attached.
     *
     * @param string $hook The hook name.
     * @param callable $function The function of this hook.
     * @param int $priority The priority this hook has.
     * @param string|null $file The optional file belonging to this hook.
     *
     * @return boolean Whether the hook was added. If the hook was already registered, it won't be registered again.
     */
    public function addHook(string $hook, callable $function, int $priority = 10, ?string $file = ""): bool
    {
        $methodRepresentation = $this->getStringRepresentationForCallable($function);

        // Check to see if we already have this hook running at this priority
        if (!empty($this->hooks[$hook][$priority][$methodRepresentation]) &&
            is_array($this->hooks[$hook][$priority][$methodRepresentation])) {
            return false;
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

            throw new \InvalidArgumentException("Invalid callable type: {$type}");
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

                        call_user_func_array($hook['function'], $arguments);
                    }
                }
            }
        } finally {
            $this->currentHook = null;
        }
    }

    /**
     * Remove a specific hook.
     *
     * @param string $hook The name of the hook.
     * @param callable $function The function of the hook.
     * @param int $priority The priority of the hook.
     *
     * @return bool Whether the hook was previously registered.
     */
    public function removeHook(string $hook, callable $function, int $priority = 10): bool
    {
        $methodRepresentation = $this->getStringRepresentationForCallable($function);

        if (isset($this->hooks[$hook][$priority][$methodRepresentation])) {
            unset($this->hooks[$hook][$priority][$methodRepresentation]);

            return true;
        }

        return false;
    }
}
