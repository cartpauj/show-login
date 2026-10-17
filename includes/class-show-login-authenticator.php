<?php

/**
 * Authentication Handler
 *
 * Handles AJAX login requests and authentication.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Authenticator class
 */
class Show_Login_Authenticator {
    /**
     * Handle AJAX login request
     *
     * Login limits are left to security plugins, which see these attempts
     * through wp_signon() like any other login.
     */
    public function handle_login_ajax(): void {
        // Verify nonce
        check_ajax_referer('show_login_nonce', 'nonce', true);

        // Get and validate credentials
        $credentials = $this->get_credentials();

        if (empty($credentials['user_login']) || empty($credentials['user_password'])) {
            wp_send_json_error([
                'message' => __('Please enter both username and password.', 'show-login')
            ]);
        }

        /**
         * Filter credentials before authentication.
         *
         * @since 1.0.0
         * @param array $credentials The credentials array.
         */
        $credentials = apply_filters('show_login_credentials', $credentials);

        /**
         * Fires before authentication attempt.
         *
         * @since 1.0.0
         * @param string $username The username being authenticated.
         */
        do_action('show_login_before_authenticate', $credentials['user_login']);

        // Attempt authentication
        $user = wp_signon($credentials, is_ssl());

        /**
         * Fires after authentication attempt.
         *
         * @since 1.0.0
         * @param WP_User|WP_Error $user The user object or error.
         * @param array $credentials The credentials used.
         */
        do_action('show_login_after_authenticate', $user, $credentials);

        // Handle authentication result
        if (is_wp_error($user)) {
            $this->handle_login_failure($user);
        } else {
            $this->handle_login_success($user);
        }
    }

    /**
     * Get credentials from POST request
     *
     * @return array Credentials array.
     */
    private function get_credentials(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce checked in handle_login_ajax() before this runs.
        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be altered.
        $remember = isset($_POST['remember']) && sanitize_text_field(wp_unslash($_POST['remember'])) === '1';
        // phpcs:enable

        return [
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ];
    }

    /**
     * Handle login failure
     *
     * @param WP_Error $error The error object.
     */
    private function handle_login_failure(WP_Error $error): void {
        // Sanitize error message to prevent username enumeration
        $error_message = $this->sanitize_login_error($error);

        /**
         * Filter error message before sending to client.
         *
         * @since 1.0.0
         * @param string $message The sanitized error message.
         * @param WP_Error $error The error object.
         */
        $error_message = apply_filters('show_login_error_message', $error_message, $error);

        wp_send_json_error(['message' => $error_message]);
    }

    /**
     * Handle login success
     *
     * @param WP_User $user The user object.
     */
    private function handle_login_success(WP_User $user): void {
        /**
         * Fires on successful login.
         *
         * @since 1.0.0
         * @param WP_User $user The user object.
         */
        do_action('show_login_success', $user);

        wp_send_json_success([
            'message' => __('Login successful! Redirecting...', 'show-login'),
        ]);
    }

    /**
     * Sanitize login error messages to prevent username enumeration
     *
     * @param WP_Error $error The error object from authentication.
     * @return string Generic error message.
     */
    private function sanitize_login_error(WP_Error $error): string {
        $error_code = $error->get_error_code();

        // Map specific error codes to generic messages
        $generic_errors = [
            'invalid_username',
            'invalid_email',
            'incorrect_password',
            'invalidcombo',
        ];

        if (in_array($error_code, $generic_errors, true)) {
            return __('<strong>Error:</strong> Invalid username or password.', 'show-login');
        }

        // For other errors, strip out username/email references
        $message = $error->get_error_message();
        $message = preg_replace(
            '/<strong>[^<]+<\/strong>\s*(is not registered|was not found)/i',
            'the username or email',
            $message
        );

        // Messages from other plugins can carry anything, and the popup shows
        // them as HTML, so only keep simple formatting and links.
        return wp_kses($message, [
            'a' => ['href' => true],
            'b' => [],
            'br' => [],
            'em' => [],
            'i' => [],
            'strong' => [],
        ]);
    }
}
