<?php

declare(strict_types = 1);

namespace MyBB\Tests\Unit;

function testAddHookWithFunctionWithNoArgsHook() {
    global $didRun;

    $didRun = true;
}

final class PluginSystemTest extends TestCase
{
    public function testAddHookWithClosureWithNoArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_closure_no_args';

        $plugins = new \MyBB\PluginSystem();

        $didRun = false;

        $plugins->addHook($hookName, function () use (&$didRun) {
            $didRun = true;
        });

        $plugins->runHooks($hookName);

        $this->assertTrue($didRun);
    }

    public function testAddHookWithClosureWithSingleArg()
    {
        $hookName = 'plugin_system_test_test_addHook_closure_single_arg';

        $plugins = new \MyBB\PluginSystem();

        $didRun = false;

        $plugins->addHook($hookName, function (int &$a) use (&$didRun) {
            $this->assertEquals(4, $a);

            $a += 1;

            $didRun  = true;
        });

        $x = 4;

        $plugins->runHooks($hookName, $x);

        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
    }

    public function testAddHookWithClosureWithMultipleArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_closure_multiple_args';

        $plugins = new \MyBB\PluginSystem();

        $didRun = false;

        $plugins->addHook($hookName, function (int &$a, string &$b) use (&$didRun) {
            $this->assertEquals(4, $a);

            $a += 1;
            $b .= ' world';

            $didRun  = true;
        });

        $x = 4;
        $y = 'hello';

        $plugins->runHooks($hookName, $x, $y);

        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
        $this->assertEquals('hello world', $y);
    }

    public function testAddHookWithClassMethodWithNoArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_class_method_no_args';

        $plugins = new \MyBB\PluginSystem();

        global $didRun;

        $didRun = false;

        $obj = new class {
            public function test()
            {
                global $didRun;

                $didRun = true;
            }
        };

        $plugins->addHook($hookName, [$obj, 'test']);

        $plugins->runHooks($hookName);

        $this->assertTrue($didRun);
    }

    public function testAddHookWithClassMethodWithSingleArg()
    {
        $hookName = 'plugin_system_test_test_addHook_class_method_single_arg';

        $plugins = new \MyBB\PluginSystem();

        global $didRun;

        $didRun = false;

        $obj = new class {
            public function test(int &$a)
            {
                global $didRun;

                $a += 1;

                $didRun  = true;
            }
        };

        $x = 4;

        $plugins->addHook($hookName, [$obj, 'test']);

        $plugins->runHooks($hookName, $x);

        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
    }

    public function testAddHookWithClassmethodWithMultipleArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_class_method_multiple_args';

        $plugins = new \MyBB\PluginSystem();

        global $didRun;

        $didRun = false;

        $obj = new class {
            public function test(int &$a, string &$b)
            {
                global $didRun;

                $a += 1;
                $b .= ' world';

                $didRun  = true;
            }
        };

        $plugins->addHook($hookName, [$obj, 'test']);

        $x = 4;
        $y = 'hello';

        $plugins->runHooks($hookName, $x, $y);

        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
        $this->assertEquals('hello world', $y);
    }
}