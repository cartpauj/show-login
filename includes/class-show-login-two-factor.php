<?php
/**
 * Two Factor Authentication Integration
 *
 * Integrates WordPress Two Factor plugin with Show Login popup.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Two_Factor class
 */
class Show_Login_Two_Factor {
    /**
     * Check if Two Factor is active
     *
     * @return bool
     */
    public static function is_two_factor_active(): bool {
        return class_exists('Two_Factor_Core');
    }

    /**
     * Initialize Two Factor integration
     */
    public static function init(): void {
        if (!self::is_two_factor_active()) {
            return;
        }

        // Intercept the 2FA redirect for AJAX requests
        add_action('wp_login', [__CLASS__, 'handle_two_factor_redirect'], 5, 2);
    }

    /**
     * Handle Two Factor redirect for AJAX requests
     *
     * This runs BEFORE Two_Factor_Core::wp_login() (priority 10) to intercept
     * the 2FA flow when Show Login is handling authentication via AJAX.
     *
     * @param string $user_login Username.
     * @param WP_User $user WP_User object.
     */
    public static function handle_two_factor_redirect(string $user_login, $user): void {
        // Only handle during Show Login AJAX requests
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }

        // Check if this is a Show Login authentication request
        if (!isset($_POST['action']) || $_POST['action'] !== 'show_login_authenticate') {
            return;
        }

        // Check if user has 2FA enabled
        if (!Two_Factor_Core::is_user_using_two_factor($user->ID)) {
            return;
        }

        // At this point, we know:
        // 1. Password is correct (wp_signon succeeded)
        // 2. User has 2FA enabled
        // 3. We're in a Show Login AJAX request

        // Prevent Two_Factor_Core from outputting HTML and exiting
        remove_action('wp_login', ['Two_Factor_Core', 'wp_login'], 10);

        // Clear the auth cookie that was just set (2FA not complete yet)
        wp_clear_auth_cookie();

        // Create a 2FA nonce for the user
        $login_nonce = Two_Factor_Core::create_login_nonce($user->ID);
        if (!$login_nonce) {
            wp_send_json_error([
                'message' => __('Failed to create two-factor authentication token.', 'show-login')
            ], 500);
        }

        // Get redirect URL from POST data (where user should go after successful 2FA)
        $redirect_to = !empty($_POST['redirect_to'])
            ? sanitize_text_field(wp_unslash($_POST['redirect_to']))
            : admin_url();

        // Build the 2FA login URL with all required parameters
        // This will show the 2FA form directly without requiring re-login
        $two_factor_url = Two_Factor_Core::login_url([
            'action' => 'validate_2fa',
            'wp-auth-id' => $user->ID,
            'wp-auth-nonce' => $login_nonce['key'],
            'redirect_to' => $redirect_to,
        ], 'login');

        // Return JSON response indicating 2FA is required
        wp_send_json_error([
            'message' => __('Redirecting to two-factor authentication...', 'show-login'),
            'two_factor_required' => true,
            'redirect_url' => $two_factor_url
        ], 200);
    }
}

// Initialize Two Factor integration
add_action('plugins_loaded', [Show_Login_Two_Factor::class, 'init'], 20);
