<?php

/**
 * PHPUnit bootstrap file.
 *
 * Loads the WordPress test library (installed into ./tmp by `composer tests-install`)
 * and the plugin under test.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

$show_login_root = dirname(__DIR__);

$tests_dir = getenv('WP_TESTS_DIR') ?: $show_login_root . '/tmp/wordpress-tests-lib';
if (!file_exists($tests_dir . '/includes/functions.php')) {
    echo "Error: the WordPress test library is missing from {$tests_dir}." . PHP_EOL;
    echo 'Run `composer tests-install` first (see README.md > Developers).' . PHP_EOL;
    exit(1);
}

if (!file_exists($show_login_root . '/vendor/autoload.php')) {
    echo 'Error: vendor/autoload.php is missing. Run `composer install` first.' . PHP_EOL;
    exit(1);
}
require_once $show_login_root . '/vendor/autoload.php';

// Tell the WP test library where the PHPUnit polyfills live.
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $show_login_root . '/vendor/yoast/phpunit-polyfills');

// No test may talk to the network.
define('WP_HTTP_BLOCK_EXTERNAL', true);

require_once $tests_dir . '/includes/functions.php';

/*
 * Keep everything the WP test bootstrap echoes ("Installing...") in a userland
 * buffer so headers_sent() stays false. That lets the plugin call header() and
 * status_header() during tests, which is how we assert HTTP status codes.
 * Flushed once, at shutdown, after the last test.
 */
ob_start();
$show_login_buffer_level = ob_get_level();
register_shutdown_function(static function () use ($show_login_buffer_level): void {
    while (ob_get_level() >= $show_login_buffer_level) {
        ob_end_flush();
    }
});

tests_add_filter('muplugins_loaded', static function () use ($show_login_root): void {
    // Stand-ins for the optional plugins Show Login integrates with.
    require_once $show_login_root . '/tests/Fixtures/two-factor.php';
    require_once $show_login_root . '/tests/Fixtures/wordfence.php';
    require_once $show_login_root . '/tests/Fixtures/turnstile.php';
    require_once $show_login_root . '/tests/Fixtures/social-login.php';
    require_once $show_login_root . '/tests/Fixtures/login-with-google.php';

    require $show_login_root . '/show-login.php';
});

// CLI runs can't send cookies; wp_signon() would otherwise warn about headers.
tests_add_filter('send_auth_cookies', '__return_false');

require $tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/Framework/TestCase.php';
require_once __DIR__ . '/Framework/AjaxTestCase.php';
