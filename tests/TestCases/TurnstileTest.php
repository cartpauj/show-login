<?php

/**
 * Simple Cloudflare Turnstile integration.
 *
 * The Turnstile plugin is replaced by tests/Fixtures/turnstile.php.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use Show_Login_Turnstile;

class TurnstileTest extends AjaxTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.30';
        $this->make_user();
    }

    private function configure_turnstile(): void
    {
        update_option('cfturnstile_key', 'site-key');
        update_option('cfturnstile_secret', 'secret-key');
        Show_Login_Turnstile::init();
    }

    private function turnstile(): array
    {
        return $GLOBALS['show_login_test_turnstile'];
    }

    public function testInactiveWithoutKeys(): void
    {
        $this->assertFalse(Show_Login_Turnstile::is_turnstile_active());

        update_option('cfturnstile_key', 'site-key');
        $this->assertFalse(Show_Login_Turnstile::is_turnstile_active(), 'Both keys are needed.');

        update_option('cfturnstile_secret', 'secret-key');
        $this->assertTrue(Show_Login_Turnstile::is_turnstile_active());
    }

    public function testInitDoesNothingWhenInactive(): void
    {
        Show_Login_Turnstile::init();

        $this->assertFalse(has_action('show_login_form_middle', [Show_Login_Turnstile::class, 'add_turnstile_field']));
        $this->assertFalse(has_action('show_login_before_authenticate', [Show_Login_Turnstile::class, 'validate_turnstile']));
        $this->assertFalse(has_filter('cfturnstile_wp_login_checks', [Show_Login_Turnstile::class, 'skip_default_wp_login_check']));
    }

    public function testInitHooksInWhenActive(): void
    {
        $this->configure_turnstile();

        $this->assertSame(10, has_action('show_login_form_middle', [Show_Login_Turnstile::class, 'add_turnstile_field']));
        $this->assertSame(10, has_action('show_login_before_authenticate', [Show_Login_Turnstile::class, 'validate_turnstile']));
        $this->assertSame(10, has_filter('cfturnstile_wp_login_checks', [Show_Login_Turnstile::class, 'skip_default_wp_login_check']));
    }

    public function testWidgetIsAddedToThePopup(): void
    {
        $this->configure_turnstile();

        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertStringContainsString('<div class="show-login-field show-login-turnstile"><div class="cf-turnstile"', $html);
        $this->assertSame(
            [['#show-login-submit', 'showLoginTurnstileCallback', 'show-login-popup', '-show-login', 'show-login-cf-turnstile']],
            $this->turnstile()['field_calls']
        );
        $this->assertLessThan(strpos($html, 'id="show-login-submit"'), strpos($html, 'show-login-turnstile'), 'Widget sits above the button.');
    }

    public function testWidgetIsHiddenForWhitelistedVisitors(): void
    {
        $this->configure_turnstile();
        $GLOBALS['show_login_test_turnstile']['whitelisted'] = true;

        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertStringNotContainsString('turnstile', $html);
    }

    public function testFieldIsNotAddedIfKeysAreRemovedLater(): void
    {
        $this->configure_turnstile();
        delete_option('cfturnstile_secret');

        ob_start();
        Show_Login_Turnstile::add_turnstile_field();
        $this->assertSame('', ob_get_clean());
    }

    public function testFailedChallengeBlocksLoginWith403(): void
    {
        $this->configure_turnstile();
        $GLOBALS['show_login_test_turnstile']['check'] = ['success' => false];
        $failed_for = null;
        add_action('cfturnstile_show_login_failed', static function ($username) use (&$failed_for) {
            $failed_for = $username;
        });

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertFalse($response['success']);
        $this->assertSame('Turnstile says no.', $response['data']['message']);
        $this->assertSame(403, $this->status);
        $this->assertSame('alice', $failed_for);
        $this->assertSame(0, get_current_user_id());
    }

    public function testMalformedCheckResultCountsAsFailure(): void
    {
        $this->configure_turnstile();
        $GLOBALS['show_login_test_turnstile']['check'] = ['success' => 'yes'];

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertFalse($response['success']);
        $this->assertSame(403, $this->status);
    }

    public function testPassedChallengeLetsLoginContinue(): void
    {
        $this->configure_turnstile();
        $passed_for = null;
        add_action('cfturnstile_show_login_success', static function ($username) use (&$passed_for) {
            $passed_for = $username;
        });

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertTrue($response['success']);
        $this->assertSame('alice', $passed_for);
        $this->assertSame(1, $this->turnstile()['check_calls'], 'Tokens are single-use: check exactly once.');
    }

    public function testWhitelistedVisitorsSkipTheCheck(): void
    {
        $this->configure_turnstile();
        $GLOBALS['show_login_test_turnstile']['whitelisted'] = true;
        $GLOBALS['show_login_test_turnstile']['check']       = ['success' => false];

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertTrue($response['success']);
        $this->assertSame(0, $this->turnstile()['check_calls']);
    }

    public function testSkipFilterSkipsTheCheck(): void
    {
        $this->configure_turnstile();
        $GLOBALS['show_login_test_turnstile']['check'] = ['success' => false];
        add_filter('show_login_skip_turnstile', '__return_true');

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertTrue($response['success']);
        $this->assertSame(0, $this->turnstile()['check_calls']);
    }

    public function testNoCheckWhenInactive(): void
    {
        Show_Login_Turnstile::validate_turnstile('alice');

        $this->assertSame(0, $this->turnstile()['check_calls']);
    }

    public function testDefaultLoginCheckIsSkippedOnlyForShowLoginRequests(): void
    {
        $_POST['action'] = 'show_login_authenticate';
        $this->assertTrue(Show_Login_Turnstile::skip_default_wp_login_check(false));

        $_POST['action'] = 'some_other_action';
        $this->assertFalse(Show_Login_Turnstile::skip_default_wp_login_check(false));
        $this->assertTrue(Show_Login_Turnstile::skip_default_wp_login_check(true));

        unset($_POST['action']);
        $this->assertFalse(Show_Login_Turnstile::skip_default_wp_login_check(false));
    }

    public function testDefaultLoginCheckRunsOutsideAjax(): void
    {
        remove_filter('wp_doing_ajax', '__return_true');
        $_POST['action'] = 'show_login_authenticate';

        $this->assertFalse(Show_Login_Turnstile::skip_default_wp_login_check(false), 'wp-login.php keeps its own check.');
    }
}
