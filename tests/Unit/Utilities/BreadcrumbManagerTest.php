<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Utilities;

use MyBB\Tests\Unit\TestCase;
use MyBB\Utilities\BreadcrumbManager;

class BreadcrumbManagerTest extends TestCase
{
    public function testConstruct()
    {
        $manager = new BreadcrumbManager('test', 'http://localhost');

        $this->assertEquals(1, $manager->count());
        $this->assertTrue(isset($manager[0]));

        $entry = $manager[0];

        $this->assertEquals('test', $entry['name']);
        $this->assertEquals('http://localhost/index.php', $entry['url']);
    }

    public function testConstructWithTrailingSlash()
    {
        $manager = new BreadcrumbManager('test', 'http://localhost/');

        $this->assertEquals('http://localhost/index.php', $manager[0]['url']);
    }

    public function testAddBreadcrumb()
    {
        $manager = new BreadcrumbManager('test', 'http://localhost');
        $manager->addBreadcrumb('forum 1', 'http://localhost/forum-1.html');

        $this->assertEquals(2, $manager->count());
    }

    public function testReset()
    {
        $manager = new BreadcrumbManager('test', 'http://localhost');
        $manager->addBreadcrumb('forum 1', 'http://localhost/forum-1.html');

        $this->assertEquals(2, $manager->count());

        $manager->reset();

        $this->assertEquals(1, $manager->count());
    }

    public function testGetIterator()
    {
        $manager = new BreadcrumbManager('test', 'http://localhost');
        $manager->addBreadcrumb('forum 1', 'http://localhost/forum-1.html');

        $iteration = 0;

        foreach ($manager as $crumb) {
            $this->assertTrue(is_array($crumb));
            $this->assertTrue(isset($crumb['name']) && isset($crumb['url']));

            switch ($iteration) {
                case 0:
                    $this->assertEquals('test', $crumb['name']);
                    $this->assertEquals('http://localhost/index.php', $crumb['url']);
                    break;
                case 1:
                    $this->assertEquals('forum 1', $crumb['name']);
                    $this->assertEquals('http://localhost/forum-1.html', $crumb['url']);
                    break;
                default:
                    $this->fail('Too many loop iterations');
                    break;
            }

            $iteration++;
        }
    }
}
