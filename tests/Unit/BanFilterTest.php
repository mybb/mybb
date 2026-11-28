<?php

namespace MyBB\Tests\Unit;

use MyBB\Tests\Traits\LegacyCoreAwareTest;
use PHPUnit\Framework\Attributes\TestWith;

class BanFilterTest extends TestCase
{
    use LegacyCoreAwareTest;

    private const IP_FILTERS = [
        1 => '192.0.2.10',
        2 => '198.51.100.*',
        3 => '10.0.*',
        4 => '203.0.113.0/28',
        5 => '2001:db8::1',
        6 => '2001:db8:1:*:*:*:*:*',
        7 => '172.16.0.0/12',
        8 => '172.16.5.*',
        9 => 'not an ip',
    ];

    private const EMAIL_FILTERS = [
        1 => '*@spam.example',
        2 => 'bad.user@example.com',
        3 => 'promo*@*',
    ];

    /**
     * @var int[] Filter IDs passed to the `lastuse` update.
     */
    private array $updatedFilters = [];

    public static function setUpBeforeClass(): void
    {
        static::setupMybb();
    }

    protected function setUp(): void
    {
        parent::setUp();

        defined('TIME_NOW') or define('TIME_NOW', time());

        $this->updatedFilters = [];

        $GLOBALS['cache']->handler = null;
        $GLOBALS['cache']->cache = [];
        $GLOBALS['db'] = $this->mockDatabase();
    }

    #[TestWith(['192.0.2.10', 1])]
    #[TestWith(['198.51.100.0', 2])]
    #[TestWith(['198.51.100.255', 2])]
    #[TestWith(['10.0.200.3', 3])]
    #[TestWith(['203.0.113.15', 4])]
    #[TestWith(['2001:db8::1', 5])]
    #[TestWith(['2001:db8:1:0:abcd::1', 6])]
    #[TestWith(['172.31.255.255', 7])]
    #[TestWith(['172.16.5.20', 7])]
    public function testBannedIpAddresses(string $ip, int $fid): void
    {
        $this->assertTrue(is_banned_ip($ip, true));
        $this->assertSame([$fid], $this->updatedFilters);
    }

    public function testIpCacheIsTextSafe(): void
    {
        $GLOBALS['cache']->update_bannedips();

        foreach ($GLOBALS['cache']->read('bannedips')['ranges'] as $ranges) {
            foreach (['lower', 'max_upper', 'max_fids'] as $field) {
                $this->assertMatchesRegularExpression('/^[0-9a-f]*$/', $ranges[$field]);
            }
        }
    }

    #[TestWith(['192.0.2.11'])]
    #[TestWith(['198.51.101.1'])]
    #[TestWith(['10.1.0.1'])]
    #[TestWith(['203.0.113.16'])]
    #[TestWith(['2001:db8::2'])]
    #[TestWith(['172.32.0.0'])]
    #[TestWith(['1.2.3.4'])]
    #[TestWith(['invalid'])]
    public function testAllowedIpAddresses(string $ip): void
    {
        $this->assertFalse(is_banned_ip($ip, true));
        $this->assertSame([], $this->updatedFilters);
    }

    public function testOutdatedIpCacheIsRebuilt(): void
    {
        $GLOBALS['cache']->cache['bannedips'] = [
            1 => ['fid' => 1, 'filter' => '192.0.2.10'],
        ];

        $this->assertTrue(is_banned_ip('198.51.100.7'));
        $this->assertArrayHasKey('ranges', $GLOBALS['cache']->read('bannedips'));
    }

    #[TestWith(['someone@spam.example', 1])]
    #[TestWith(['SOMEONE@SPAM.EXAMPLE', 1])]
    #[TestWith(['someone@spam.example.org', 1])]
    #[TestWith(['bad.user@example.com', 2])]
    #[TestWith(['promotions@shop.example', 3])]
    public function testBannedEmailAddresses(string $email, int $fid): void
    {
        $this->assertTrue(is_banned_email($email, true));
        $this->assertSame([$fid], $this->updatedFilters);
    }

    #[TestWith(['someone@example.com'])]
    #[TestWith(['good.user@example.com'])]
    #[TestWith(['someone@spam-example.com'])]
    #[TestWith(['pro@shop.example'])]
    public function testAllowedEmailAddresses(string $email): void
    {
        $this->assertFalse(is_banned_email($email, true));
        $this->assertSame([], $this->updatedFilters);
    }

    public function testOutdatedEmailCacheIsRebuilt(): void
    {
        $GLOBALS['cache']->cache['bannedemails'] = [
            1 => ['fid' => 1, 'filter' => '*@other.example'],
        ];

        $this->assertTrue(is_banned_email('someone@spam.example'));
        $this->assertArrayHasKey('filters', $GLOBALS['cache']->read('bannedemails'));
    }

    /**
     * Mocks the queries used to build the ban caches and record `lastuse` updates.
     */
    private function mockDatabase(): \Mockery\MockInterface
    {
        $db = \Mockery::mock(\DB_Base::class);

        $rows = [];
        $db->shouldReceive('simple_select')->andReturnUsing(function ($table, $fields, $conditions) use (&$rows) {
            $filters = str_contains($conditions, '3') ? self::EMAIL_FILTERS : self::IP_FILTERS;
            $rows = [];
            foreach ($filters as $fid => $filter) {
                $rows[] = ['fid' => (string)$fid, 'filter' => $filter];
            }
            return 'query';
        });
        $db->shouldReceive('fetch_array')->andReturnUsing(function () use (&$rows) {
            return array_shift($rows) ?? false;
        });
        $db->shouldReceive('escape_string')->andReturnArg(0);
        $db->shouldReceive('replace_query');
        $db->shouldReceive('update_query')->andReturnUsing(function ($table, $values, $where) {
            preg_match("/fid='(\d+)'/", $where, $match);
            $this->updatedFilters[] = (int)$match[1];
        });

        return $db;
    }
}
