<?php
/**
 * Cloudflare Turnstile Integration
 *
 * Integrates Simple Cloudflare Turnstile with Show Login popup.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Turnstile class
 */
class Show_Login_Turnstile {
    /**
     * Check if Turnstile is active and configured
     *
     * @return bool
     */
    public static function is_turnstile_active(): bool {
        // Check if the Turnstile plugin function exists and keys are configured
        return function_exists('cfturnstile_field_show') &&
               !empty(get_option('cfturnstile_key')) &&
               !empty(get_option('cfturnstile_secret'));
    }

    /**
     * Initialize Turnstile integration
     */
    public static function init(): void {
        if (!self::is_turnstile_active()) {
            return;
        }

        // Add Turnstile widget to the login form
        add_action('show_login_form_middle', [__CLASS__, 'add_turnstile_field']);

        // Validate Turnstile before authentication
        add_action('show_login_before_authenticate', [__CLASS__, 'validate_turnstile']);

        // Note: Turnstile scripts are loaded dynamically via JavaScript in show-login.js
        // This ensures compatibility with full-page caching since URL parameters don't
        // trigger cache variations. The loadTurnstileScript() function handles loading.
    }

    /**
     * Add Turnstile field to login form
     */
    public static function add_turnstile_field(): void {
        if (!self::is_turnstile_active()) {
            return;
        }

        // Check if whitelisted (uses Turnstile's whitelist functionality)
        if (function_exists('cfturnstile_whitelisted') && cfturnstile_whitelisted()) {
            return;
        }

        // Display Turnstile widget
        echo '<div class="show-login-field show-login-turnstile">';

        // Use Turnstile's display function
        // Parameters: button_id, callback, form_name, unique_id, class
        if (function_exists('cfturnstile_field_show')) {
            cfturnstile_field_show(
                '#show-login-submit',
                'showLoginTurnstileCallback',
                'show-login-popup',
                '-show-login',
                'show-login-cf-turnstile'
            );
        }

        echo '</div>';
    }

    /**
     * Validate Turnstile before authentication
     *
     * @param string $username Username being authenticated.
     */
    public static function validate_turnstile(string $username): void {
        if (!self::is_turnstile_active()) {
            return;
        }

        // Skip validation for whitelisted users/IPs
        if (function_exists('cfturnstile_whitelisted') && cfturnstile_whitelisted()) {
            return;
        }

        // Allow custom skip filter
        if (apply_filters('show_login_skip_turnstile', false)) {
            return;
        }

        // Validate Turnstile response
        if (!function_exists('cfturnstile_check')) {
            wp_send_json_error([
                'message' => __('Turnstile validation is not available.', 'show-login')
            ], 500);
        }

        $check = cfturnstile_check();

        if (!isset($check['success']) || $check['success'] !== true) {
            // Get custom error message if available
            $error_message = function_exists('cfturnstile_failed_message')
                ? cfturnstile_failed_message()
                : __('Please verify you are human.', 'show-login');

            // Fire Turnstile failure action
            do_action('cfturnstile_show_login_failed', $username);

            wp_send_json_error([
                'message' => $error_message
            ], 403);
        }

        // Fire success action
        do_action('cfturnstile_show_login_success', $username);
    }
}

// Initialize Turnstile integration
add_action('plugins_loaded', [Show_Login_Turnstile::class, 'init'], 20);
