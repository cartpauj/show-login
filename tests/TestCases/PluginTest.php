<?php

/**
 * Plugin bootstrap and main controller.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\TestCases;

use ShowLogin\Tests\Framework\TestCase;
use Show_Login;

class PluginTest extends TestCase
{
    public function testConstantsAreDefined(): void
    {
        $this->assertSame(Show_Login::VERSION, SHOW_LOGIN_VERSION);
        $this->assertSame(realpath(dirname(__DIR__, 2) . '/show-login.php'), realpath(SHOW_LOGIN_FILE));
        $this->assertSame(trailingslashit(dirname(SHOW_LOGIN_FILE)), SHOW_LOGIN_PATH);
        $this->assertStringEndsWith('/', SHOW_LOGIN_URL);
    }

    /**
     * Every place the version lives must agree, or a release ships mislabeled.
     * See RELEASES.md.
     */
    public function testVersionNumbersMatchEverywhere(): void
    {
        $header = get_file_data(SHOW_LOGIN_FILE, ['Version' => 'Version'])['Version'];
        $readme = get_file_data(dirname(SHOW_LOGIN_FILE) . '/readme.txt', ['Stable' => 'Stable tag'])['Stable'];

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $header, 'Plugin header Version must be x.y.z');
        $this->assertSame($header, SHOW_LOGIN_VERSION, 'SHOW_LOGIN_VERSION in show-login.php');
        $this->assertSame($header, Show_Login::VERSION, 'Show_Login::VERSION in includes/class-show-login.php');
        $this->assertSame($header, $readme, 'Stable tag in readme.txt');
    }

    public function testInitFunctionIsHookedEarlyAndReturnsTheSingleton(): void
    {
        $this->assertSame(1, has_action('plugins_loaded', 'show_login_init'));

        show_login_init();

        $this->assertSame(Show_Login::get_instance(), Show_Login::get_instance());
    }

    public function testConstructorRegistersHooks(): void
    {
        $plugin = $this->fresh_instance();

        $this->assertSame(10, has_action('plugins_loaded', [$plugin, 'load_textdomain']));
        $this->assertSame(10, has_action('wp', [$plugin, 'setup_frontend_hooks']));
        $this->assertNotFalse(has_action('wp_ajax_nopriv_show_login_authenticate'));
        $this->assertNotFalse(has_action('wp_ajax_nopriv_show_login_check_popup'));
        $this->assertNotFalse(has_action('wp_ajax_show_login_check_popup'));
        // Logged-in users have nothing to log in to.
        $this->assertFalse(has_action('wp_ajax_show_login_authenticate'));
    }

    public function testPathAndUrlGetters(): void
    {
        $plugin = $this->fresh_instance();

        $this->assertSame(SHOW_LOGIN_PATH, $plugin->get_plugin_path());
        $this->assertSame(SHOW_LOGIN_URL, $plugin->get_plugin_url());
    }

    public function testFrontendHooksEnqueueAssetsOnTheFrontEnd(): void
    {
        $plugin = $this->fresh_instance();
        remove_all_actions('wp_enqueue_scripts');

        $plugin->setup_frontend_hooks();

        $this->assertTrue(has_action('wp_enqueue_scripts'));
    }

    public function testFrontendHooksSkipTheAdmin(): void
    {
        $plugin = $this->fresh_instance();
        remove_all_actions('wp_enqueue_scripts');
        set_current_screen('dashboard');

        $plugin->setup_frontend_hooks();

        $this->assertFalse(has_action('wp_enqueue_scripts'));
        set_current_screen('front');
    }

    public function testLoadTextdomainPointsAtThePluginsLanguagesFolder(): void
    {
        global $wp_textdomain_registry;
        $plugin = $this->fresh_instance();

        $plugin->load_textdomain();

        $paths = new \ReflectionProperty($wp_textdomain_registry, 'custom_paths');
        $paths->setAccessible(true);
        $this->assertStringEndsWith(
            '/' . basename(dirname(SHOW_LOGIN_FILE)) . '/languages',
            $paths->getValue($wp_textdomain_registry)['show-login']
        );
    }

    /**
     * Builds a brand-new Show_Login so the constructor runs inside the test.
     */
    private function fresh_instance(): Show_Login
    {
        $property = new \ReflectionProperty(Show_Login::class, 'instance');
        $property->setAccessible(true);
        $original = $property->getValue();
        $property->setValue(null, null);

        $plugin = Show_Login::get_instance(SHOW_LOGIN_FILE);

        // Put the real instance back so later tests see the bootstrapped plugin.
        $property->setValue(null, $original);

        return $plugin;
    }
}
