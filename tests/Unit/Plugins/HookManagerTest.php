<?php

declare(strict_types = 1);

namespace MyBB\Tests\Unit\Plugins;

use MyBB\Plugins\HookManager;
use MyBB\Tests\Unit\TestCase;

function testAddHookWithFunctionWithNoArgsHook()
{
    global $didRun;

    $didRun = true;
}

function testAddHookWithFunctionWithSingleArgHook(int &$a)
{
    global $didRun;
    $a += 1;
    $didRun  = true;
}

function testAddHookWithFunctionWithMultipleArgsHook(int &$a, string &$b)
{
    global $didRun;
    $a += 1;
    $b .= ' world';
    $didRun  = true;
}

final class HookManagerTest extends TestCase
{
    public function testAddHookWithClosureWithNoArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_closure_no_args';
        $plugins = new HookManager();
        $didRun = false;
        $this->assertTrue($plugins->addHook($hookName, function () use (&$didRun, $hookName, $plugins) {
            $didRun = true;
            $this->assertEquals($hookName, $plugins->getCurrentHook());
        }));
        $plugins->runHooks($hookName);
        $this->assertTrue($didRun);
    }

    public function testAddHookWithClosureWithSingleArg()
    {
        $hookName = 'plugin_system_test_test_addHook_closure_single_arg';
        $plugins = new HookManager();
        $didRun = false;
        $this->assertTrue($plugins->addHook($hookName, function (int &$a) use (&$didRun, $hookName, $plugins) {
            $this->assertEquals(4, $a);
            $a += 1;
            $didRun  = true;
            $this->assertEquals($hookName, $plugins->getCurrentHook());
        }));
        $x = 4;
        $plugins->runHooks($hookName, $x);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
    }

    public function testAddHookWithClosureWithMultipleArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_closure_multiple_args';
        $plugins = new HookManager();
        $didRun = false;
        $this->assertTrue(
            $plugins->addHook($hookName, function (int &$a, string &$b) use (&$didRun, $hookName, $plugins) {
                $this->assertEquals(4, $a);
                $a += 1;
                $b .= ' world';
                $didRun  = true;
                $this->assertEquals($hookName, $plugins->getCurrentHook());
            })
        );
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
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $obj = new class {
            public function test()
            {
                global $didRun;
                $didRun = true;
            }
        };
        $this->assertTrue($plugins->addHook($hookName, [$obj, 'test']));
        $plugins->runHooks($hookName);
        $this->assertTrue($didRun);
    }

    public function testAddHookWithClassMethodWithSingleArg()
    {
        $hookName = 'plugin_system_test_test_addHook_class_method_single_arg';
        $plugins = new HookManager();
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
        $this->assertTrue($plugins->addHook($hookName, [$obj, 'test']));
        $plugins->runHooks($hookName, $x);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
    }

    public function testAddHookWithClassMethodWithMultipleArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_class_method_multiple_args';
        $plugins = new HookManager();
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
        $this->assertTrue($plugins->addHook($hookName, [$obj, 'test']));
        $x = 4;
        $y = 'hello';
        $plugins->runHooks($hookName, $x, $y);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
        $this->assertEquals('hello world', $y);
    }

    public function testAddHookWithStaticMethodWithNoArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_static_method_no_args';
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $obj = new class {
            public static function test()
            {
                global $didRun;
                $didRun = true;
            }
        };
        $this->assertTrue($plugins->addHook($hookName, [get_class($obj), 'test']));
        $plugins->runHooks($hookName);
        $this->assertTrue($didRun);
    }

    public function testAddHookWithStaticMethodWithSingleArg()
    {
        $hookName = 'plugin_system_test_test_addHook_static_method_single_arg';
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $obj = new class {
            public static function test(int &$a)
            {
                global $didRun;
                $a += 1;
                $didRun  = true;
            }
        };
        $x = 4;
        $this->assertTrue($plugins->addHook($hookName, [get_class($obj), 'test']));
        $plugins->runHooks($hookName, $x);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
    }

    public function testAddHookWithStaticMethodWithMultipleArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_static_method_multiple_args';
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $obj = new class {
            public static function test(int &$a, string &$b)
            {
                global $didRun;
                $a += 1;
                $b .= ' world';
                $didRun  = true;
            }
        };
        $this->assertTrue($plugins->addHook($hookName, [get_class($obj), 'test']));
        $x = 4;
        $y = 'hello';
        $plugins->runHooks($hookName, $x, $y);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
        $this->assertEquals('hello world', $y);
    }

    public function testAddHookWithStandardFunctionWithNoArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_static_method_no_args';
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $this->assertTrue(
            $plugins->addHook($hookName, 'MyBB\Tests\Unit\Plugins\testAddHookWithFunctionWithNoArgsHook')
        );
        $plugins->runHooks($hookName);
        $this->assertTrue($didRun);
    }

    public function testAddHookWithStandardFunctionWithSingleArg()
    {
        $hookName = 'plugin_system_test_test_addHook_static_method_single_arg';
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $x = 4;
        $this->assertTrue(
            $plugins->addHook($hookName, 'MyBB\Tests\Unit\Plugins\testAddHookWithFunctionWithSingleArgHook')
        );
        $plugins->runHooks($hookName, $x);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
    }

    public function testAddHookWithSStandardFunctionWithMultipleArgs()
    {
        $hookName = 'plugin_system_test_test_addHook_static_method_multiple_args';
        $plugins = new HookManager();
        global $didRun;
        $didRun = false;
        $this->assertTrue(
            $plugins->addHook($hookName, 'MyBB\Tests\Unit\Plugins\testAddHookWithFunctionWithMultipleArgsHook')
        );
        $x = 4;
        $y = 'hello';
        $plugins->runHooks($hookName, $x, $y);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
        $this->assertEquals('hello world', $y);
    }

    public function testAddHookWithDuplicateHook()
    {
        $hookName = 'plugin_system_test_test_addHook_duplicate_hook';
        $plugins = new HookManager();
        $didRun = false;
        $func = function (int &$a, string &$b) use (&$didRun) {
            $a += 1;
            $b .= ' world';
            $didRun  = true;
        };
        $this->assertTrue($plugins->addHook($hookName, $func));
        $this->assertTrue($plugins->addHook($hookName, $func));
        $x = 4;
        $y = 'hello';
        $plugins->runHooks($hookName, $x, $y);
        $this->assertTrue($didRun);
        $this->assertEquals(5, $x);
        $this->assertEquals('hello world', $y);
    }

    public function testRemoveHook()
    {
        $hookName = 'plugin_system_test_test_removeHook';
        $plugins = new HookManager();
        $didRun = false;
        $func = function (int &$a, string &$b) use (&$didRun, $hookName, $plugins) {
            $a += 1;
            $b .= ' world';
            $didRun  = true;
            $this->assertEquals($hookName, $plugins->getCurrentHook());
        };
        $this->assertTrue($plugins->addHook($hookName, $func));
        $this->assertTrue($plugins->removeHook($hookName, $func));
        $x = 4;
        $y = 'hello';
        $plugins->runHooks($hookName, $x, $y);
        $this->assertFalse($didRun);
        $this->assertEquals(4, $x);
        $this->assertEquals('hello', $y);
        $this->assertEmpty($plugins->getCurrentHook());
    }
}
