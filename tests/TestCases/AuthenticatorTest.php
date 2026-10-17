<?php

/**
 * The show_login_authenticate ajax endpoint.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use WP_Error;
use WPAjaxDieStopException;

class AuthenticatorTest extends AjaxTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        $this->make_user();
    }

    public function testCorrectPasswordLogsIn(): void
    {
        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertTrue($response['success']);
        $this->assertSame('Login successful! Redirecting...', $response['data']['message']);
    }

    public function testEmailWorksAsUsername(): void
    {
        $response = $this->ajax('show_login_authenticate', $this->login_post('alice@example.org'));

        $this->assertTrue($response['success']);
    }

    public function testSuccessFiresHooksInOrder(): void
    {
        $fired = [];
        add_action('show_login_before_authenticate', static function ($username) use (&$fired) {
            $fired[] = "before:{$username}";
        });
        add_action('show_login_after_authenticate', static function ($user, $credentials) use (&$fired) {
            $fired[] = 'after:' . $user->user_login . ':' . $credentials['user_login'];
        }, 10, 2);
        add_action('show_login_success', static function ($user) use (&$fired) {
            $fired[] = "success:{$user->user_login}";
        });

        $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertSame(['before:alice', 'after:alice:alice', 'success:alice'], $fired);
    }

    public function testRememberMeIsPassedToWordPress(): void
    {
        $remember = null;
        add_filter('show_login_credentials', static function ($credentials) use (&$remember) {
            $remember = $credentials['remember'];
            return $credentials;
        });

        $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', ['remember' => '1']));
        $this->assertTrue($remember);

        $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', ['remember' => 'yes']));
        $this->assertFalse($remember, 'Only "1" turns on remember-me.');
    }

    public function testPasswordIsNotMangled(): void
    {
        $this->make_user('bob', 'p@ss <word> \\ "quoted" %20');

        $response = $this->ajax(
            'show_login_authenticate',
            // Ajax input arrives slashed, like real $_POST data.
            $this->login_post('bob', wp_slash('p@ss <word> \\ "quoted" %20'))
        );

        $this->assertTrue($response['success']);
    }

    public function testCredentialsFilterCanChangeTheLogin(): void
    {
        add_filter('show_login_credentials', static function ($credentials) {
            $credentials['user_password'] = 'correct horse';
            return $credentials;
        });

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));

        $this->assertTrue($response['success']);
    }

    /**
     * @dataProvider missingFieldProvider
     */
    public function testMissingFieldsAreRejectedBeforeAuthenticating(array $post): void
    {
        $authenticated = false;
        add_action('show_login_before_authenticate', static function () use (&$authenticated) {
            $authenticated = true;
        });

        $response = $this->ajax('show_login_authenticate', array_merge(['nonce' => wp_create_nonce('show_login_nonce')], $post));

        $this->assertFalse($response['success']);
        $this->assertSame('Please enter both username and password.', $response['data']['message']);
        $this->assertFalse($authenticated);
    }

    public function missingFieldProvider(): array
    {
        return [
            'nothing'       => [[]],
            'no password'   => [['username' => 'alice']],
            'no username'   => [['password' => 'correct horse']],
            'blank strings' => [['username' => '  ', 'password' => '']],
        ];
    }

    public function testBadNonceIsRejected(): void
    {
        $this->expectException(WPAjaxDieStopException::class);
        $this->expectExceptionMessage('-1');

        $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', ['nonce' => 'nope']));
    }

    public function testMissingNonceIsRejected(): void
    {
        $post = $this->login_post();
        unset($post['nonce']);

        $this->expectException(WPAjaxDieStopException::class);

        $this->ajax('show_login_authenticate', $post);
    }

    public function testWrongPasswordGivesAGenericError(): void
    {
        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));

        $this->assertFalse($response['success']);
        $this->assertSame('<strong>Error:</strong> Invalid username or password.', $response['data']['message']);
    }

    /**
     * Wrong password and unknown user must look identical (no username enumeration).
     */
    public function testUnknownUserAndWrongPasswordLookTheSame(): void
    {
        $wrong_password = $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));
        $unknown_user   = $this->ajax('show_login_authenticate', $this->login_post('nobody', 'wrong'));
        $unknown_email  = $this->ajax('show_login_authenticate', $this->login_post('nobody@example.org', 'wrong'));

        $this->assertSame($wrong_password, $unknown_user);
        $this->assertSame($wrong_password, $unknown_email);
    }

    public function testOtherErrorsHaveUsernamesStripped(): void
    {
        add_filter('authenticate', static function () {
            return new WP_Error('custom', '<strong>alice</strong> is not registered on this site.');
        }, 100);

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertSame('the username or email on this site.', $response['data']['message']);
    }

    public function testOtherErrorsPassThroughOtherwise(): void
    {
        add_filter('authenticate', static function () {
            return new WP_Error('account_locked', 'Your account is locked.');
        }, 100);

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertSame('Your account is locked.', $response['data']['message']);
    }

    /**
     * The popup shows errors as HTML, so another plugin's error must not be able to run script.
     */
    public function testOtherErrorsKeepOnlySimpleHtml(): void
    {
        add_filter('authenticate', static function () {
            return new WP_Error(
                'account_pending',
                '<strong>Pending:</strong> <img src=x onerror=alert(1)>check <a href="https://example.org/help" onclick="x()">help</a>'
            );
        }, 100);

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertSame(
            '<strong>Pending:</strong> check <a href="https://example.org/help">help</a>',
            $response['data']['message']
        );
    }

    public function testErrorMessageFilter(): void
    {
        $code = null;
        add_filter('show_login_error_message', static function ($message, $error) use (&$code) {
            $code = $error->get_error_code();
            return 'Nope.';
        }, 10, 2);

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));

        $this->assertSame('Nope.', $response['data']['message']);
        $this->assertSame('incorrect_password', $code);
    }

    /**
     * Login limits are left to security plugins, which hook wp_login_failed and authenticate.
     */
    public function testRepeatedFailuresNeverLockOutTheRightPassword(): void
    {
        $failed = 0;
        add_action('wp_login_failed', static function () use (&$failed) {
            $failed++;
        });

        for ($i = 0; $i < 10; $i++) {
            $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));
        }
        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertSame(10, $failed, 'Security plugins must see every failed attempt.');
        $this->assertTrue($response['success']);
    }

    public function testLoggedInUsersCannotReachTheEndpoint(): void
    {
        wp_set_current_user(self::factory()->user->create());

        $this->assertFalse(has_action('wp_ajax_show_login_authenticate'));
    }
}
