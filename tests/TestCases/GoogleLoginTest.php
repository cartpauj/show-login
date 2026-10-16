<?php

/**
 * rtCamp Login with Google integration.
 *
 * The plugin is replaced by tests/Fixtures/login-with-google.php.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use Show_Login_Google;
use Show_Login_Test_Google;

class GoogleLoginTest extends AjaxTestCase
{
    public function tear_down(): void
    {
        wp_dequeue_style('login-with-google');
        wp_deregister_style('login-with-google');
        parent::tear_down();
    }

    private function configure(): void
    {
        Show_Login_Test_Google::$settings = new \Show_Login_Test_Google_Settings('id', 'secret');
    }

    public function testHooksIntoThePopupAfterSocialLogin(): void
    {
        remove_all_actions('show_login_social_buttons');
        remove_all_actions('wp_enqueue_scripts');

        Show_Login_Google::init();

        $this->assertSame(20, has_action('show_login_social_buttons', [Show_Login_Google::class, 'render_button']));
        $this->assertSame(20, has_action('wp_enqueue_scripts', [Show_Login_Google::class, 'enqueue_styles']));
    }

    public function testNeedsBothClientIdAndSecret(): void
    {
        $this->assertFalse(Show_Login_Google::is_google_login_active());

        Show_Login_Test_Google::$settings = new \Show_Login_Test_Google_Settings('id', '');
        $this->assertFalse(Show_Login_Google::is_google_login_active());

        $this->configure();
        $this->assertTrue(Show_Login_Google::is_google_login_active());
    }

    public function testInactiveIfThePluginErrors(): void
    {
        $this->configure();
        Show_Login_Test_Google::$container_throws = true;

        $this->assertFalse(Show_Login_Google::is_google_login_active());

        ob_start();
        Show_Login_Google::render_button(home_url('/'));
        $this->assertSame('', ob_get_clean());
    }

    public function testNoButtonIfThePluginHasNoShortcodeService(): void
    {
        $this->configure();
        Show_Login_Test_Google::$shortcode_missing = true;

        ob_start();
        Show_Login_Google::render_button(home_url('/'));
        $this->assertSame('', ob_get_clean());
    }

    public function testNoButtonWhenNotConfigured(): void
    {
        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertStringNotContainsString('wp_google_login', $html);
        $this->assertSame([], Show_Login_Test_Google::$shortcode_calls);
    }

    public function testButtonAppearsAboveTheFormAndReturnsToThePage(): void
    {
        $this->configure();

        $html = $this->ajax('show_login_check_popup', [
            'current_url' => home_url('/members/area/?view=list&show_login=1'),
        ])['data']['html'];

        $this->assertSame([['redirect_to' => home_url('/members/area/?view=list')]], Show_Login_Test_Google::$shortcode_calls);
        $this->assertStringContainsString('<div class="show-login-social">', $html);
        $this->assertStringContainsString('class="wp_google_login__button"', $html);
        $this->assertLessThan(strpos($html, '<form id="show-login-form"'), strpos($html, 'wp_google_login'));
    }

    public function testGoogleComesAfterCaseproofSocialLogin(): void
    {
        $this->configure();
        $GLOBALS['show_login_test_social']['mpsl'] = new \Show_Login_Test_Social_Handler([
            new \Show_Login_Test_Social_Provider('facebook', 'Facebook'),
        ]);

        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertLessThan(strpos($html, 'wp_google_login'), strpos($html, 'cspfsl-social-login-btn'));
        $this->assertSame(1, substr_count($html, 'show-login-divider'));
    }

    public function testStylesRegisteredAndLoadedWhenConfigured(): void
    {
        $this->configure();

        Show_Login_Google::enqueue_styles();

        $this->assertTrue(wp_style_is('login-with-google', 'enqueued'));
    }

    public function testStylesUseTheExistingRegistration(): void
    {
        $this->configure();
        wp_register_style('login-with-google', 'https://example.org/already-registered.css');

        Show_Login_Google::enqueue_styles();

        $this->assertSame('https://example.org/already-registered.css', wp_styles()->registered['login-with-google']->src);
        $this->assertTrue(wp_style_is('login-with-google', 'enqueued'));
    }

    public function testStylesSkippedWhenNotConfiguredOrLoggedIn(): void
    {
        Show_Login_Google::enqueue_styles();
        $this->assertFalse(wp_style_is('login-with-google', 'enqueued'));

        $this->configure();
        wp_set_current_user(self::factory()->user->create());
        Show_Login_Google::enqueue_styles();
        $this->assertFalse(wp_style_is('login-with-google', 'enqueued'));
    }
}
