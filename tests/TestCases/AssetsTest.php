<?php

/**
 * Asset loading and redirect URL building.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\TestCase;
use Show_Login_Assets;

class AssetsTest extends TestCase
{
    private Show_Login_Assets $assets;

    public function set_up(): void
    {
        parent::set_up();
        $this->assets = new Show_Login_Assets('9.9.9', 'https://example.org/wp-content/plugins/show-login/');
    }

    public function tear_down(): void
    {
        wp_dequeue_style('show-login');
        wp_deregister_style('show-login');
        wp_dequeue_script('show-login');
        wp_deregister_script('show-login');
        parent::tear_down();
    }

    public function testEnqueuesStyleAndScriptWithVersion(): void
    {
        $this->assets->enqueue_assets();

        $this->assertTrue(wp_style_is('show-login', 'enqueued'));
        $this->assertTrue(wp_script_is('show-login', 'enqueued'));

        $style  = wp_styles()->registered['show-login'];
        $script = wp_scripts()->registered['show-login'];
        $this->assertSame('https://example.org/wp-content/plugins/show-login/assets/css/show-login.css', $style->src);
        $this->assertSame('https://example.org/wp-content/plugins/show-login/assets/js/show-login.js', $script->src);
        $this->assertSame('9.9.9', $style->ver);
        $this->assertSame('9.9.9', $script->ver);
        $this->assertSame([], $script->deps, 'The script must not depend on jQuery or anything else.');
    }

    public function testLocalizesAjaxUrlAndLoadingFlag(): void
    {
        $this->assets->enqueue_assets();

        $data = wp_scripts()->get_data('show-login', 'data');
        $this->assertStringContainsString('var showLoginData', $data);
        $this->assertStringContainsString('"ajaxUrl":"' . admin_url('admin-ajax.php') . '"', $data);
        $this->assertStringContainsString('"suppressLoading":""', $data);
        $this->assertStringContainsString('"turnstile":""', $data);
        // The nonce is fetched over AJAX so it is never baked into cached HTML.
        $this->assertStringNotContainsString('nonce', $data);
    }

    public function testSuppressLoadingFilterIsPassedToScript(): void
    {
        add_filter('show_login_suppress_loading_state', '__return_true');

        $this->assets->enqueue_assets();

        $this->assertStringContainsString('"suppressLoading":"1"', wp_scripts()->get_data('show-login', 'data'));
    }

    public function testTurnstileFlagIsOnWhenTurnstileIsConfigured(): void
    {
        update_option('cfturnstile_key', 'site-key');
        update_option('cfturnstile_secret', 'secret-key');

        $this->assets->enqueue_assets();

        $this->assertStringContainsString('"turnstile":"1"', wp_scripts()->get_data('show-login', 'data'));
    }

    public function testDefaultButtonColors(): void
    {
        $this->assets->enqueue_assets();

        $this->assertSame(
            '#show-login-submit { background: #0073aa; color: #fff; } #show-login-submit:hover { background: #005a87; }',
            implode('', wp_styles()->get_data('show-login', 'after'))
        );
    }

    public function testButtonColorFilters(): void
    {
        add_filter('show_login_button_bg_color', static fn() => '#e74c3c');
        add_filter('show_login_button_hover_bg_color', static fn() => '#c0392b');
        add_filter('show_login_button_text_color', static fn() => '#000000');

        $this->assets->enqueue_assets();

        $css = implode('', wp_styles()->get_data('show-login', 'after'));
        $this->assertStringContainsString('background: #e74c3c; color: #000000;', $css);
        $this->assertStringContainsString(':hover { background: #c0392b; }', $css);
    }

    public function testButtonColorFiltersCannotInjectCss(): void
    {
        add_filter('show_login_button_bg_color', static fn() => 'red; } body { display:none');

        $this->assets->enqueue_assets();

        $css = implode('', wp_styles()->get_data('show-login', 'after'));
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringContainsString('#show-login-submit { background: ;', $css);
    }

    /**
     * @dataProvider redirectProvider
     */
    public function testRedirectUrlDropsOnlyTheTriggerParameters(string $request_uri, string $expected_path): void
    {
        $_SERVER['HTTP_HOST']   = wp_parse_url(home_url(), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = $request_uri;

        $this->assertSame('http://' . $_SERVER['HTTP_HOST'] . $expected_path, $this->assets->get_redirect_url());
    }

    public function redirectProvider(): array
    {
        return [
            'sl=1'               => ['/page/?sl=1', '/page/'],
            'sl=true'            => ['/page/?sl=true', '/page/'],
            'show_login=1'       => ['/page/?show_login=1', '/page/'],
            'keeps other params' => ['/shop/?id=123&sl=true&color=red', '/shop/?id=123&color=red'],
            'both triggers'      => ['/?sl=1&show_login=true&x=1', '/?x=1'],
            'no trigger'         => ['/about/?a=b', '/about/?a=b'],
        ];
    }

    public function testRedirectUrlUsesHttpsWhenTheRequestIsSecure(): void
    {
        $_SERVER['HTTPS']       = 'on';
        $_SERVER['HTTP_HOST']   = wp_parse_url(home_url(), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = '/secure/?sl=1';

        $this->assertSame('https://' . $_SERVER['HTTP_HOST'] . '/secure/', $this->assets->get_redirect_url());
    }

    public function testRedirectUrlFilterReceivesBothUrls(): void
    {
        $_SERVER['HTTP_HOST']   = wp_parse_url(home_url(), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = '/a/?sl=1';
        $args                   = null;
        add_filter('show_login_redirect_url', static function ($redirect, $current) use (&$args) {
            $args = [$redirect, $current];
            return home_url('/dashboard/');
        }, 10, 2);

        $this->assertSame(home_url('/dashboard/'), $this->assets->get_redirect_url());
        $this->assertSame(['http://' . $_SERVER['HTTP_HOST'] . '/a/', 'http://' . $_SERVER['HTTP_HOST'] . '/a/?sl=1'], $args);
    }

    public function testRedirectUrlRefusesOffsiteDestinations(): void
    {
        $_SERVER['HTTP_HOST']   = 'evil.example.com';
        $_SERVER['REQUEST_URI'] = '/phish/?sl=1';

        $this->assertSame(home_url(), $this->assets->get_redirect_url());
    }

    public function testRedirectUrlFilterCannotSendUsersOffsite(): void
    {
        add_filter('show_login_redirect_url', static fn() => 'https://evil.example.com/');

        $this->assertSame(home_url(), $this->assets->get_redirect_url());
    }

    public function testRedirectUrlWithoutARequestFallsBackSafely(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

        $this->assertStringNotContainsString('evil', $this->assets->get_redirect_url());
        $this->assertTrue(
            in_array($this->assets->get_redirect_url(), ['', home_url()], true),
            'Expected an empty (relative) URL or the home URL.'
        );
    }
}
