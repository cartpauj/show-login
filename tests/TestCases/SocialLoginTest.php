<?php

/**
 * Caseproof social login integration (MemberPress / WishList Member / MemberCore).
 *
 * The add-ons are replaced by tests/Fixtures/social-login.php.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use Show_Login_Social_Login;
use Show_Login_Test_Social_Handler as Handler;
use Show_Login_Test_Social_Provider as Provider;

class SocialLoginTest extends AjaxTestCase
{
    public function tear_down(): void
    {
        wp_dequeue_style('cspfsl-buttons-style');
        wp_deregister_style('cspfsl-buttons-style');
        parent::tear_down();
    }

    private function use_memberpress(array $providers): void
    {
        $GLOBALS['show_login_test_social']['mpsl'] = new Handler($providers);
    }

    /**
     * Pulls the decoded "redir" value out of each social login link in the popup.
     */
    private function redirect_targets(string $html): array
    {
        preg_match_all('/class="cspfsl-social-login-btn" href="([^"]+)"/', $html, $matches);

        return array_map(static function ($href) {
            parse_str((string) wp_parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
            return base64_decode($query['redir']);
        }, $matches[1]);
    }

    public function testHooksIntoThePopup(): void
    {
        remove_all_actions('show_login_social_buttons');
        remove_all_actions('wp_enqueue_scripts');

        Show_Login_Social_Login::init();

        $this->assertSame(10, has_action('show_login_social_buttons', [Show_Login_Social_Login::class, 'render_buttons']));
        $this->assertSame(20, has_action('wp_enqueue_scripts', [Show_Login_Social_Login::class, 'enqueue_styles']));
    }

    public function testNothingWhenNoAddonIsInstalled(): void
    {
        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertFalse(Show_Login_Social_Login::is_social_login_active());
        $this->assertStringNotContainsString('show-login-social', $html);
        $this->assertStringNotContainsString('show-login-divider', $html);
    }

    public function testNothingWhenNoProviderIsEnabledAndVerified(): void
    {
        $this->use_memberpress([
            new Provider('google', 'Google', false, true),
            new Provider('facebook', 'Facebook', true, false),
        ]);

        $this->assertSame([], Show_Login_Social_Login::get_providers());
        $this->assertStringNotContainsString('show-login-social', $this->ajax('show_login_check_popup')['data']['html']);
    }

    public function testShowsOneButtonPerEnabledProviderAboveTheForm(): void
    {
        $this->use_memberpress([
            new Provider('google', 'Google'),
            new Provider('facebook', 'Facebook', false),
            new Provider('linkedin', 'LinkedIn'),
        ]);

        $html = $this->ajax('show_login_check_popup', ['current_url' => home_url('/page/?sl=1')])['data']['html'];

        $this->assertSame(2, substr_count($html, 'cspfsl-social-login-btn'));
        $this->assertStringContainsString('<span class="google-login-btn"><span class="icon"></span><span class="text google-login-span-text">Log in with Google</span></span>', $html);
        $this->assertStringContainsString('Log in with LinkedIn', $html);
        $this->assertStringNotContainsString('Facebook', $html);
        $this->assertStringContainsString('memberpress-social-login-request=google', $html);
        $this->assertStringContainsString('type=wp_login', $html);

        $this->assertLessThan(strpos($html, 'show-login-divider'), strpos($html, 'show-login-social'));
        $this->assertLessThan(strpos($html, '<form id="show-login-form"'), strpos($html, 'show-login-divider'));
    }

    public function testButtonsReturnTheUserToThePageTheyWereOn(): void
    {
        $this->use_memberpress([new Provider('google', 'Google'), new Provider('linkedin', 'LinkedIn')]);

        $html = $this->ajax('show_login_check_popup', [
            'current_url' => home_url('/courses/lesson-1/?tab=notes&sl=1'),
        ])['data']['html'];

        $this->assertSame(
            [home_url('/courses/lesson-1/?tab=notes'), home_url('/courses/lesson-1/?tab=notes')],
            $this->redirect_targets($html)
        );
    }

    /**
     * Base64 can contain "+" and "/"; a bare "+" in a query string turns into a space.
     */
    public function testRedirectSurvivesBase64SpecialCharacters(): void
    {
        $this->use_memberpress([new Provider('google', 'Google')]);
        $url = home_url('/?p=>>>&q=???');
        $this->assertMatchesRegularExpression('/[+\/]/', base64_encode($url), 'Test URL must produce + or / in base64.');

        ob_start();
        Show_Login_Social_Login::render_buttons($url);

        $this->assertSame([$url], $this->redirect_targets(ob_get_clean()));
    }

    public function testFirstAddonWithProvidersWins(): void
    {
        $GLOBALS['show_login_test_social']['mpsl']  = new Handler([]);
        $GLOBALS['show_login_test_social']['wlmsl'] = new Handler([new Provider('google', 'Google', true, true, 'wishlist_member')]);
        $GLOBALS['show_login_test_social']['mcsl']  = new Handler([new Provider('google', 'Google', true, true, 'membercore')]);

        ob_start();
        Show_Login_Social_Login::render_buttons(home_url('/'));
        $html = ob_get_clean();

        $this->assertSame(1, substr_count($html, 'cspfsl-social-login-btn'));
        $this->assertStringContainsString('wishlist_member-social-login-request=google', $html);
    }

    public function testIgnoresBrokenHandlers(): void
    {
        $GLOBALS['show_login_test_social']['mpsl']             = new \stdClass();
        $GLOBALS['show_login_test_social']['wlmsl']            = new Handler();
        $GLOBALS['show_login_test_social']['wlmsl']->oauthProviders = 'not-an-array';

        $this->assertSame([], Show_Login_Social_Login::get_providers());
    }

    public function testProviderIdCannotBreakTheMarkup(): void
    {
        $this->use_memberpress([new Provider('goo"gle><script>', 'Goo<b>gle</b>')]);

        ob_start();
        Show_Login_Social_Login::render_buttons(home_url('/'));
        $html = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('Log in with Goo&lt;b&gt;gle&lt;/b&gt;', $html);
    }

    public function testStylesLoadForLoggedOutVisitorsWhenActive(): void
    {
        wp_register_style('cspfsl-buttons-style', 'https://example.org/buttons.css');
        $this->use_memberpress([new Provider('google', 'Google')]);

        Show_Login_Social_Login::enqueue_styles();

        $this->assertTrue(wp_style_is('cspfsl-buttons-style', 'enqueued'));
    }

    public function testStylesSkippedWhenInactive(): void
    {
        wp_register_style('cspfsl-buttons-style', 'https://example.org/buttons.css');

        Show_Login_Social_Login::enqueue_styles();

        $this->assertFalse(wp_style_is('cspfsl-buttons-style', 'enqueued'));
    }

    public function testStylesSkippedForLoggedInUsers(): void
    {
        wp_register_style('cspfsl-buttons-style', 'https://example.org/buttons.css');
        $this->use_memberpress([new Provider('google', 'Google')]);
        wp_set_current_user(self::factory()->user->create());

        Show_Login_Social_Login::enqueue_styles();

        $this->assertFalse(wp_style_is('cspfsl-buttons-style', 'enqueued'));
    }
}
