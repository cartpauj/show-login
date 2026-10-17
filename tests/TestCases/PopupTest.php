<?php

/**
 * The show_login_check_popup ajax endpoint and popup template.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\AjaxTestCase;
use Show_Login_Assets;
use Show_Login_Popup;

class PopupTest extends AjaxTestCase
{
    public function testLoggedOutVisitorsGetTheForm(): void
    {
        $response = $this->ajax('show_login_check_popup', ['current_url' => home_url('/page/?sl=1')]);

        $this->assertTrue($response['success']);
        $data = $response['data'];
        $this->assertTrue($data['show']);
        $this->assertTrue($data['suppressLoading']);
        $this->assertStringContainsString('<form id="show-login-form"', $data['html']);
        $this->assertSame(1, wp_verify_nonce($data['nonce'], 'show_login_nonce'));
        $this->assertSame(home_url('/page/'), $data['redirectUrl']);
    }

    public function testLoggedInVisitorsAreToldSo(): void
    {
        wp_set_current_user(self::factory()->user->create());

        $response = $this->ajax('show_login_check_popup');

        $this->assertSame(
            ['show' => false, 'reason' => 'already_logged_in', 'suppressLoading' => true],
            $response['data']
        );
    }

    public function testRedirectKeepsOtherQueryArgs(): void
    {
        $response = $this->ajax('show_login_check_popup', [
            'current_url' => home_url('/shop/?id=7&show_login=true&sort=asc'),
        ]);

        $this->assertSame(home_url('/shop/?id=7&sort=asc'), $response['data']['redirectUrl']);
    }

    public function testRedirectKeepsPercentEncodedCharacters(): void
    {
        $response = $this->ajax('show_login_check_popup', [
            'current_url' => wp_slash(home_url('/search/?q=caf%C3%A9%20menu&sl=1')),
        ]);

        // remove_query_arg() re-encodes the space as "+", which means the same thing.
        $this->assertSame(home_url('/search/?q=caf%C3%A9+menu'), $response['data']['redirectUrl']);
    }

    public function testOffsiteCurrentUrlIsReplacedWithHome(): void
    {
        $response = $this->ajax('show_login_check_popup', ['current_url' => 'https://evil.example.com/?sl=1']);

        $this->assertSame(home_url(), $response['data']['redirectUrl']);
    }

    /**
     * The browser always sends current_url, so the filter must run on that path.
     */
    public function testRedirectFilterChangesWhereThePageSendsPeople(): void
    {
        $args = null;
        add_filter('show_login_redirect_url', static function ($redirect, $current) use (&$args) {
            $args = [$redirect, $current];
            return home_url('/welcome/');
        }, 10, 2);

        $response = $this->ajax('show_login_check_popup', ['current_url' => home_url('/shop/?sl=1')]);

        $this->assertSame(home_url('/welcome/'), $response['data']['redirectUrl']);
        $this->assertSame([home_url('/shop/'), home_url('/shop/?sl=1')], $args);
    }

    public function testRedirectFilterCannotSendPeopleOffSite(): void
    {
        add_filter('show_login_redirect_url', static fn() => 'https://evil.example.com/');

        $response = $this->ajax('show_login_check_popup', ['current_url' => home_url('/shop/')]);

        $this->assertSame(home_url(), $response['data']['redirectUrl']);
    }

    /**
     * A junk current_url (only possible from a hand-made request) means "reload this page",
     * not "go to admin-ajax.php".
     */
    public function testUnsafeCurrentUrlMakesThePageReload(): void
    {
        $_SERVER['HTTP_HOST']   = wp_parse_url(home_url(), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';

        $response = $this->ajax('show_login_check_popup', ['current_url' => 'javascript:alert(1)']);

        $this->assertSame('', $response['data']['redirectUrl']);
    }

    public function testWithoutCurrentUrlTheRequestUrlIsUsed(): void
    {
        $_SERVER['HTTP_HOST']   = wp_parse_url(home_url(), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = '/from-server/?sl=1';

        $response = $this->ajax('show_login_check_popup');

        $this->assertSame(home_url('/from-server/'), $response['data']['redirectUrl']);
    }

    /**
     * The spinner delay lives in the browser; sleeping here would tie up a PHP worker.
     */
    public function testLoadingStateDoesNotMakeTheServerWait(): void
    {
        remove_filter('show_login_suppress_loading_state', '__return_true');

        $start    = microtime(true);
        $response = $this->ajax('show_login_check_popup');

        $this->assertLessThan(0.5, microtime(true) - $start);
        $this->assertFalse($response['data']['suppressLoading']);
    }

    public function testMissingTemplateGivesEmptyHtml(): void
    {
        $popup = new Show_Login_Popup('/does/not/exist/', new Show_Login_Assets('1', ''));
        remove_all_actions('wp_ajax_nopriv_show_login_check_popup');
        add_action('wp_ajax_nopriv_show_login_check_popup', [$popup, 'handle_check_popup_ajax']);

        $response = $this->ajax('show_login_check_popup');

        $this->assertSame('', $response['data']['html']);
    }

    public function testTemplateHasAccessibleFields(): void
    {
        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertStringContainsString('id="show-login-close" type="button" aria-label="Close"', $html);
        $this->assertStringContainsString('<div id="show-login-error" role="alert"></div>', $html);
        $this->assertStringContainsString('<label for="show-login-username">', $html);
        $this->assertStringContainsString('autocomplete="username"', $html);
        $this->assertStringContainsString('<label for="show-login-password">', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString('name="remember"', $html);
        $this->assertStringContainsString('id="show-login-submit"', $html);
    }

    public function testTemplateLabelFiltersAreEscaped(): void
    {
        add_filter('show_login_popup_title', static fn() => 'Welcome <script>x</script>');
        add_filter('show_login_username_label', static fn() => 'Email Address');
        add_filter('show_login_password_label', static fn() => 'Secret');
        add_filter('show_login_remember_label', static fn() => 'Keep me in');
        add_filter('show_login_submit_label', static fn() => 'Sign In');

        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertStringContainsString('<h2>Welcome &lt;script&gt;x&lt;/script&gt;</h2>', $html);
        $this->assertStringContainsString('Email Address', $html);
        $this->assertStringContainsString('Secret', $html);
        $this->assertStringContainsString('Keep me in', $html);
        $this->assertStringContainsString('Sign In', $html);
    }

    public function testSocialHookGetsTheRedirectUrlAndAddsADivider(): void
    {
        $received = null;
        add_action('show_login_social_buttons', static function ($redirect_url) use (&$received) {
            $received = $redirect_url;
            echo '<a class="my-sso" href="#">SSO</a>';
        });

        $html = $this->ajax('show_login_check_popup', ['current_url' => home_url('/x/?sl=1&y=2')])['data']['html'];

        $this->assertSame(home_url('/x/?y=2'), $received);
        $this->assertMatchesRegularExpression(
            '#<div class="show-login-social">\s*<a class="my-sso" href="\#">SSO</a>\s*</div>\s*<div class="show-login-divider"><span>or</span></div>#',
            $html
        );
    }

    public function testNoDividerWhenSocialHookPrintsOnlyWhitespace(): void
    {
        add_action('show_login_social_buttons', static function () {
            echo "  \n ";
        });

        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $this->assertStringNotContainsString('show-login-social', $html);
        $this->assertStringNotContainsString('show-login-divider', $html);
    }

    public function testTemplateActionsRenderInOrder(): void
    {
        foreach (['show_login_after_title', 'show_login_form_start', 'show_login_form_middle', 'show_login_form_end'] as $hook) {
            add_action($hook, static function () use ($hook) {
                echo "<i>{$hook}</i>";
            });
        }

        $html = $this->ajax('show_login_check_popup')['data']['html'];

        $positions = [
            strpos($html, '<i>show_login_after_title</i>'),
            strpos($html, '<form id="show-login-form"'),
            strpos($html, '<i>show_login_form_start</i>'),
            strpos($html, 'id="show-login-username"'),
            strpos($html, '<i>show_login_form_middle</i>'),
            strpos($html, 'id="show-login-submit"'),
            strpos($html, '<i>show_login_form_end</i>'),
            strpos($html, '</form>'),
        ];
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }
}
