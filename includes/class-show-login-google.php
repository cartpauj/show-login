<?php

/**
 * Login with Google Integration
 *
 * Adds the button from rtCamp's Login with Google plugin to the popup.
 *
 * @package ShowLogin
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Google class
 */
class Show_Login_Google {
    /**
     * Initialize Login with Google integration
     */
    public static function init(): void {
        // After any Caseproof social login buttons.
        add_action('show_login_social_buttons', [__CLASS__, 'render_button'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles'], 20);
    }

    /**
     * Get a service from the Login with Google plugin
     *
     * @param string $id Service id, e.g. "settings".
     * @return object|null
     */
    private static function service(string $id) {
        if (!function_exists('RtCamp\GoogleLogin\plugin')) {
            return null;
        }

        try {
            return \RtCamp\GoogleLogin\plugin()->container()->get($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if Login with Google is active and has its client ID and secret set
     *
     * @return bool
     */
    public static function is_google_login_active(): bool {
        $settings = self::service('settings');
        if ($settings === null) {
            return false;
        }

        // Read into variables first: these are magic properties without __isset(),
        // so empty($settings->client_id) would always be true.
        $client_id = $settings->client_id;
        $client_secret = $settings->client_secret;

        return !empty($client_id) && !empty($client_secret);
    }

    /**
     * Enqueue the button styles
     *
     * The plugin only loads them on wp-login.php, and the popup is injected
     * after page load, so they must already be on the page.
     */
    public static function enqueue_styles(): void {
        if (is_user_logged_in() || !self::is_google_login_active()) {
            return;
        }

        $assets = self::service('assets');
        if (!wp_style_is('login-with-google', 'registered') && $assets && method_exists($assets, 'register_login_styles')) {
            $assets->register_login_styles();
        }

        wp_enqueue_style('login-with-google');
    }

    /**
     * Output the Login with Google button
     *
     * @param string $redirect_url Where to send the user after they log in.
     */
    public static function render_button(string $redirect_url): void {
        if (!self::is_google_login_active()) {
            return;
        }

        $shortcode = self::service('shortcode');
        if (!$shortcode || !method_exists($shortcode, 'callback')) {
            return;
        }

        // Always pass redirect_to: during an ajax request the plugin would
        // otherwise send the user back to admin-ajax.php.
        echo $shortcode->callback(['redirect_to' => $redirect_url]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The plugin's template escapes its output.
    }
}

// Initialize Login with Google integration
add_action('plugins_loaded', [Show_Login_Google::class, 'init'], 20);
