<?php

/**
 * IP-based rate limiting.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\TestCase;
use Show_Login_Rate_Limiter;

class RateLimiterTest extends TestCase
{
    private Show_Login_Rate_Limiter $limiter;

    public function set_up(): void
    {
        parent::set_up();
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $this->limiter          = new Show_Login_Rate_Limiter();
    }

    public function testNotLimitedWithNoAttempts(): void
    {
        $this->assertSame(['is_limited' => false, 'time_remaining' => 0], $this->limiter->is_rate_limited());
    }

    public function testLimitedAfterFiveFailuresByDefault(): void
    {
        $this->fail_times(4);
        $this->assertFalse($this->limiter->is_rate_limited()['is_limited'], '4 failures should still be allowed');

        $this->fail_times(1);
        $result = $this->limiter->is_rate_limited();

        $this->assertTrue($result['is_limited']);
        $this->assertGreaterThan(0, $result['time_remaining']);
        $this->assertLessThanOrEqual(60, $result['time_remaining']);
    }

    public function testAttemptsAndExpiryAreStoredPerIpInATransient(): void
    {
        $this->fail_times(2);

        $record = get_transient('show_login_attempts_' . md5('203.0.113.10'));
        $this->assertSame(2, $record['count']);
        $this->assertEqualsWithDelta(time() + 60, $record['expires'], 2);
    }

    public function testMaxAttemptsFilter(): void
    {
        add_filter('show_login_max_attempts', static fn() => 2);

        $this->fail_times(1);
        $this->assertFalse($this->limiter->is_rate_limited()['is_limited']);
        $this->fail_times(1);
        $this->assertTrue($this->limiter->is_rate_limited()['is_limited']);
    }

    public function testWindowFilterSetsTheLockoutLength(): void
    {
        add_filter('show_login_rate_limit_window', static fn() => 3600);

        $this->fail_times(5);
        $remaining = $this->limiter->is_rate_limited()['time_remaining'];

        $this->assertGreaterThan(3500, $remaining);
        $this->assertLessThanOrEqual(3600, $remaining);
    }

    /**
     * 1.0.0 stored a plain number. Lockouts in progress during an upgrade must still hold.
     */
    public function testOldPlainCountTransientsStillLimit(): void
    {
        set_transient('show_login_attempts_' . md5('203.0.113.10'), 5, 60);

        $this->assertSame(['is_limited' => true, 'time_remaining' => 0], $this->limiter->is_rate_limited());

        $this->fail_times(1);
        $this->assertSame(6, get_transient('show_login_attempts_' . md5('203.0.113.10'))['count']);
    }

    public function testExpiredRecordReportsNoTimeRemaining(): void
    {
        set_transient('show_login_attempts_' . md5('203.0.113.10'), ['count' => 5, 'expires' => time() - 10], 60);

        $this->assertSame(['is_limited' => true, 'time_remaining' => 0], $this->limiter->is_rate_limited());
    }

    public function testWorksWithAPersistentObjectCache(): void
    {
        $was_using = wp_using_ext_object_cache(true);

        $this->fail_times(5);
        $result = $this->limiter->is_rate_limited();

        wp_using_ext_object_cache($was_using);
        $this->assertTrue($result['is_limited']);
        $this->assertGreaterThan(50, $result['time_remaining']);
    }

    public function testClearResetsTheCounter(): void
    {
        $this->fail_times(5);

        $this->limiter->clear_failed_attempts();

        $this->assertFalse($this->limiter->is_rate_limited()['is_limited']);
        $this->assertFalse(get_transient('show_login_attempts_' . md5('203.0.113.10')));
    }

    public function testDifferentIpsAreCountedSeparately(): void
    {
        $this->fail_times(5);

        $_SERVER['REMOTE_ADDR'] = '203.0.113.99';

        $this->assertFalse($this->limiter->is_rate_limited()['is_limited']);
    }

    /**
     * @dataProvider ipProvider
     */
    public function testClientIpDetection(array $server, string $expected): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        foreach ($server as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $this->assertSame($expected, $this->invoke($this->limiter, 'get_client_ip'));
    }

    public function ipProvider(): array
    {
        return [
            'remote addr only'          => [['REMOTE_ADDR' => '198.51.100.1'], '198.51.100.1'],
            'cloudflare wins'           => [[
                'HTTP_CF_CONNECTING_IP' => '198.51.100.2',
                'HTTP_X_REAL_IP'        => '198.51.100.3',
                'REMOTE_ADDR'           => '198.51.100.4',
            ], '198.51.100.2'],
            'x-real-ip before xff'      => [[
                'HTTP_X_REAL_IP'       => '198.51.100.3',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.5',
            ], '198.51.100.3'],
            'first xff entry'           => [['HTTP_X_FORWARDED_FOR' => '198.51.100.6, 10.0.0.1, 10.0.0.2'], '198.51.100.6'],
            'invalid header is skipped' => [[
                'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
                'REMOTE_ADDR'           => '198.51.100.7',
            ], '198.51.100.7'],
            'ipv6'                      => [['REMOTE_ADDR' => '2001:db8::1'], '2001:db8::1'],
        ];
    }

    public function testClientIpFilter(): void
    {
        add_filter('show_login_client_ip', static fn() => '192.0.2.55');

        $this->assertSame('192.0.2.55', $this->invoke($this->limiter, 'get_client_ip'));
    }

    private function fail_times(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->limiter->log_failed_attempt();
        }
    }
}
