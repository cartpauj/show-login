<?php

/**
 * Base test case for the plugin's admin-ajax endpoints.
 *
 * @package ShowLogin\Tests
 */

declare(strict_types=1);

namespace ShowLogin\Tests\Framework;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

abstract class AjaxTestCase extends WP_Ajax_UnitTestCase
{
    /**
     * Last HTTP status code the endpoint sent, or null if none.
     *
     * @var int|null
     */
    protected ?int $status = null;

    private array $server_backup = [];

    public function set_up(): void
    {
        parent::set_up();
        $this->server_backup = $_SERVER;
        $this->status        = null;
        \Two_Factor_Core::reset();
        \show_login_test_wordfence_reset();
        \show_login_test_turnstile_reset();
        \show_login_test_social_reset();
        \Show_Login_Test_Google::reset();

        add_filter('status_header', function ($header, $code) {
            $this->status = (int) $code;
            return $header;
        }, 10, 2);

        // Tests expect the popup-check endpoint to report suppressLoading as true.
        add_filter('show_login_suppress_loading_state', '__return_true');
    }

    public function tear_down(): void
    {
        $_SERVER = $this->server_backup;
        parent::tear_down();
    }

    /**
     * Runs an ajax action the way admin-ajax.php would (nopriv when logged out)
     * and returns the decoded JSON response.
     *
     * @param string $action Ajax action name.
     * @param array  $post   POST data.
     * @return array Decoded JSON response.
     */
    protected function ajax(string $action, array $post = []): array
    {
        $_POST                = $post;
        $_POST['action']      = $action;
        $_REQUEST             = $_POST;
        $this->_last_response = '';

        $hook = is_user_logged_in() ? "wp_ajax_{$action}" : "wp_ajax_nopriv_{$action}";

        // The die handler closes this buffer itself; only close it if it's still ours.
        $level = ob_get_level();
        ob_start();
        try {
            do_action($hook);
        } catch (WPAjaxDieContinueException $e) {
            // Expected: wp_send_json_*() ends with wp_die().
            unset($e);
        } finally {
            if (ob_get_level() > $level) {
                $this->_last_response .= ob_get_clean();
            }
        }

        $this->assertNotSame('', $this->_last_response, "The {$hook} action sent no response.");
        $decoded = json_decode($this->_last_response, true);
        $this->assertIsArray($decoded, 'Response was not JSON: ' . $this->_last_response);

        return $decoded;
    }

    /**
     * Creates a user with a known password.
     */
    protected function make_user(string $login = 'alice', string $password = 'correct horse'): \WP_User
    {
        $id = self::factory()->user->create([
            'user_login' => $login,
            'user_pass'  => $password,
            'user_email' => $login . '@example.org',
        ]);
        return get_user_by('id', $id);
    }

    /**
     * A valid login POST body.
     */
    protected function login_post(string $login = 'alice', string $password = 'correct horse', array $extra = []): array
    {
        return array_merge([
            'nonce'    => wp_create_nonce('show_login_nonce'),
            'username' => $login,
            'password' => $password,
        ], $extra);
    }
}
