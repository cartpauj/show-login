<?php

/**
 * Two Factor plugin integration.
 *
 * The Two Factor plugin is replaced by tests/Fixtures/two-factor.php.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use Show_Login_Two_Factor;
use Two_Factor_Core;

class TwoFactorTest extends AjaxTestCase
{
    private \WP_User $user;

    public function set_up(): void
    {
        parent::set_up();
        $this->user = $this->make_user();

        // What the real Two Factor plugin hooks in.
        add_action('wp_login', [Two_Factor_Core::class, 'wp_login'], 10, 2);
    }

    public function testDetectsTheTwoFactorPlugin(): void
    {
        $this->assertTrue(Show_Login_Two_Factor::is_two_factor_active());
    }

    public function testHooksInBeforeTwoFactor(): void
    {
        remove_all_actions('wp_login');

        Show_Login_Two_Factor::init();

        $this->assertSame(5, has_action('wp_login', [Show_Login_Two_Factor::class, 'handle_two_factor_redirect']));
    }

    public function testUsersWithTwoFactorAreSentToTheTwoFactorForm(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];
        $cleared                         = false;
        add_action('clear_auth_cookie', static function () use (&$cleared) {
            $cleared = true;
        });
        $succeeded = false;
        add_action('show_login_success', static function () use (&$succeeded) {
            $succeeded = true;
        });

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', [
            'redirect_to' => home_url('/members/'),
        ]));

        $this->assertFalse($response['success']);
        $this->assertSame(200, $this->status, 'Sent as a normal 200 so the JS reads it.');
        $this->assertTrue($response['data']['two_factor_required']);
        $this->assertSame('Redirecting to two-factor authentication...', $response['data']['message']);

        $url = wp_parse_url($response['data']['redirect_url']);
        parse_str($url['query'], $query);
        $this->assertSame('/wp-login.php', $url['path']);
        $this->assertSame('validate_2fa', $query['action']);
        $this->assertSame((string) $this->user->ID, $query['wp-auth-id']);
        $this->assertSame('test-2fa-nonce', $query['wp-auth-nonce']);
        $this->assertSame(home_url('/members/'), $query['redirect_to']);

        $this->assertTrue($cleared, 'Auth cookies are cleared until 2FA is done.');
        $this->assertSame(0, Two_Factor_Core::$wp_login_calls, "Two Factor's own HTML handler must not run.");
        $this->assertFalse($succeeded, 'show_login_success must not fire before 2FA.');
    }

    public function testRedirectKeepsPercentEncodedCharacters(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', [
            'redirect_to' => home_url('/search/?q=a%20b'),
        ]));

        parse_str(wp_parse_url($response['data']['redirect_url'], PHP_URL_QUERY), $query);
        $this->assertSame(home_url('/search/?q=a%20b'), $query['redirect_to']);
    }

    public function testOffSiteRedirectFallsBackToTheDashboard(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', [
            'redirect_to' => 'https://evil.example/',
        ]));

        parse_str(wp_parse_url($response['data']['redirect_url'], PHP_URL_QUERY), $query);
        $this->assertSame(admin_url(), $query['redirect_to']);
    }

    public function testRedirectDefaultsToTheDashboard(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        parse_str(wp_parse_url($response['data']['redirect_url'], PHP_URL_QUERY), $query);
        $this->assertSame(admin_url(), $query['redirect_to']);
    }

    public function testNonceFailureReturns500(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];
        Two_Factor_Core::$login_nonce    = false;

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertFalse($response['success']);
        $this->assertSame(500, $this->status);
        $this->assertSame('Failed to create two-factor authentication token.', $response['data']['message']);
    }

    public function testUsersWithoutTwoFactorLogInNormally(): void
    {
        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertTrue($response['success']);
        $this->assertSame(1, Two_Factor_Core::$wp_login_calls, 'Two Factor still sees the login.');
    }

    public function testWrongPasswordNeverReachesTwoFactor(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));

        $this->assertArrayNotHasKey('two_factor_required', $response['data']);
    }

    public function testIgnoresLoginsOutsideAjax(): void
    {
        remove_filter('wp_doing_ajax', '__return_true');
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];
        $_POST['action']                 = 'show_login_authenticate';

        Show_Login_Two_Factor::handle_two_factor_redirect('alice', $this->user);

        $this->assertSame(10, has_action('wp_login', [Two_Factor_Core::class, 'wp_login']), 'wp-login.php flow untouched.');
    }

    public function testIgnoresOtherAjaxActions(): void
    {
        Two_Factor_Core::$users_with_2fa = [$this->user->ID];
        $_POST['action']                 = 'some_other_action';

        Show_Login_Two_Factor::handle_two_factor_redirect('alice', $this->user);

        $this->assertSame(10, has_action('wp_login', [Two_Factor_Core::class, 'wp_login']));
    }
}
