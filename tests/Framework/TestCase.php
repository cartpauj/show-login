<?php

/**
 * Base test case.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\Framework;

use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase
{
    /**
     * Server vars tests commonly touch; restored after every test.
     *
     * @var array
     */
    private array $server_backup = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->server_backup = $_SERVER;
        \Two_Factor_Core::reset();
        \show_login_test_turnstile_reset();
        \show_login_test_social_reset();
        \Show_Login_Test_Google::reset();
    }

    public function tear_down(): void
    {
        $_SERVER = $this->server_backup;
        $_POST   = [];
        parent::tear_down();
    }

    /**
     * Calls a private/protected method.
     *
     * @param object|string $object_or_class Instance, or class name for static methods.
     * @param string        $method          Method name.
     * @param array         $args            Arguments.
     * @return mixed
     */
    protected function invoke($object_or_class, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($object_or_class, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs(is_object($object_or_class) ? $object_or_class : null, $args);
    }
}
