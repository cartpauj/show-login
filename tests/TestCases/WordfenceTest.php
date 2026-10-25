<?php

/**
 * Wordfence Login Security integration.
 *
 * Wordfence is replaced by tests/Fixtures/wordfence.php.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use Show_Login_Wordfence;
use WP_Error;

class WordfenceTest extends AjaxTestCase
{
    private \WP_User $user;

    public function set_up(): void
    {
        parent::set_up();
        $this->user = $this->make_user();
    }

    private function turn_on_2fa(): void
    {
        $GLOBALS['show_login_test_wordfence']['users_with_2fa'] = [$this->user->ID];
    }

    public function testDetectsWordfence(): void
    {
        $this->assertTrue(Show_Login_Wordfence::is_wordfence_active());
    }

    public function testInitAddsTheCodeFieldToTheForm(): void
    {
        remove_all_actions('show_login_form_middle');

        Show_Login_Wordfence::init();

        $this->assertSame(10, has_action('show_login_form_middle', [Show_Login_Wordfence::class, 'render_code_field']));
    }

    public function testPopupHasAHiddenDisabledCodeField(): void
    {
        $response = $this->ajax('show_login_check_popup', ['current_url' => home_url('/page/?sl=1')]);
        $html     = $response['data']['html'];

        $this->assertMatchesRegularExpression('/id="show-login-2fa-field"\s+hidden/', $html);
        $this->assertStringContainsString('name="wfls-token"', $html);
        $this->assertMatchesRegularExpression('/id="show-login-2fa-code"[^>]*\sdisabled/s', $html, 'Disabled so it is not sent with normal logins.');
        $this->assertStringContainsString('autocomplete="one-time-code"', $html);
    }

    public function testCodeFieldLabelCanBeFiltered(): void
    {
        add_filter('show_login_2fa_code_label', static fn() => 'Your code');

        ob_start();
        Show_Login_Wordfence::render_code_field();
        $html = ob_get_clean();

        $this->assertStringContainsString('Your code', $html);
    }

    public function testUsersWith2faAreAskedForTheirCode(): void
    {
        $this->turn_on_2fa();
        $succeeded = false;
        add_action('show_login_success', static function () use (&$succeeded) {
            $succeeded = true;
        });

        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertFalse($response['success']);
        $this->assertTrue($response['data']['code_required']);
        $this->assertSame('Enter the code from your authenticator app.', $response['data']['message']);
        $this->assertFalse($succeeded, 'Not logged in until the code checks out.');
    }

    public function testCorrectCodeLogsIn(): void
    {
        $this->turn_on_2fa();

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', ['wfls-token' => '123456']));

        $this->assertTrue($response['success']);
    }

    public function testWrongCodeShowsWordfencesError(): void
    {
        $this->turn_on_2fa();

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'correct horse', ['wfls-token' => '000000']));

        $this->assertFalse($response['success']);
        $this->assertArrayNotHasKey('code_required', $response['data']);
        $this->assertStringContainsString('CODE INVALID', $response['data']['message']);
    }

    public function testWrongPasswordDoesNotAskForACode(): void
    {
        $this->turn_on_2fa();

        $response = $this->ajax('show_login_authenticate', $this->login_post('alice', 'wrong'));

        $this->assertFalse($response['success']);
        $this->assertArrayNotHasKey('code_required', $response['data'], 'Asking for a code would reveal the password was right.');
        $this->assertSame('<strong>Error:</strong> Invalid username or password.', $response['data']['message']);
    }

    public function testOnlyWordfencesCodeRequiredErrorCounts(): void
    {
        $this->assertTrue(Show_Login_Wordfence::is_code_required(new WP_Error('wfls_twofactor_required', 'x')));
        $this->assertFalse(Show_Login_Wordfence::is_code_required(new WP_Error('wfls_twofactor_failed', 'x')));
        $this->assertFalse(Show_Login_Wordfence::is_code_required(new WP_Error('incorrect_password', 'x')));
    }

    public function testUsersWithout2faLogInNormally(): void
    {
        $response = $this->ajax('show_login_authenticate', $this->login_post());

        $this->assertTrue($response['success']);
    }
}
