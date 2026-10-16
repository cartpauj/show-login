<?php

/**
 * Minimal stand-in for the Two Factor plugin's Two_Factor_Core class.
 *
 * Only implements the methods Show Login calls. Tests drive it through the
 * public static properties.
 *
 * @package ShowLogin\Tests
 */

// phpcs:disable

class Two_Factor_Core {
    /** @var int[] User IDs that have two-factor enabled. */
    public static $users_with_2fa = [];

    /** @var array|false Value returned by create_login_nonce(). */
    public static $login_nonce = ['key' => 'test-2fa-nonce', 'expiration' => 0];

    /** @var int Number of times wp_login() ran. */
    public static $wp_login_calls = 0;

    public static function reset(): void {
        self::$users_with_2fa = [];
        self::$login_nonce    = ['key' => 'test-2fa-nonce', 'expiration' => 0];
        self::$wp_login_calls = 0;
    }

    public static function is_user_using_two_factor($user_id = null): bool {
        return in_array((int) $user_id, self::$users_with_2fa, true);
    }

    public static function create_login_nonce($user_id) {
        return self::$login_nonce;
    }

    // Same as the real plugin: values are URL-encoded before being added.
    public static function login_url($params = [], $scheme = 'login'): string {
        return add_query_arg(urlencode_deep($params), site_url('wp-login.php', $scheme));
    }

    public static function wp_login($user_login, $user): void {
        self::$wp_login_calls++;
    }
}
