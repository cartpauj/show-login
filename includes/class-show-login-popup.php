<?php

/**
 * Popup Handler
 *
 * Handles popup HTML rendering and AJAX popup check endpoint.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Popup class
 */
class Show_Login_Popup {
    /**
     * Plugin directory path
     *
     * @var string
     */
    private string $plugin_path;

    /**
     * Assets handler
     *
     * @var Show_Login_Assets
     */
    private Show_Login_Assets $assets;

    /**
     * Constructor
     *
     * @param string $plugin_path Plugin directory path.
     * @param Show_Login_Assets $assets Assets handler instance.
     */
    public function __construct(string $plugin_path, Show_Login_Assets $assets) {
        $this->plugin_path = $plugin_path;
        $this->assets = $assets;
    }

    /**
     * Handle AJAX request to check if popup should be shown
     * Cache-compatible: Always executes fresh PHP with user's cookies
     */
    public function handle_check_popup_ajax(): void {
        /**
         * Filter to suppress loading spinner and status messages.
         * If true, popup stays hidden until AJAX confirms user is logged out.
         *
         * @since 1.0.0
         * @param bool $suppress Whether to suppress loading state (default: false).
         */
        $suppress_loading = apply_filters('show_login_suppress_loading_state', false);

        // No delay here: the browser keeps the spinner up long enough not to flash.
        // Sleeping would tie up a server worker on every anonymous request.

        // Check if user is already logged in
        if (is_user_logged_in()) {
            wp_send_json_success([
                'show' => false,
                'reason' => 'already_logged_in',
                'suppressLoading' => $suppress_loading
            ]);
        }

        // Get redirect URL from POST data (current page URL sent from JS)
        // This ensures we redirect to the actual page, not admin-ajax.php
        // No nonce: this endpoint is what hands out the nonce. The URL is
        // validated as same-site by get_redirect_url().
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $current_url = !empty($_POST['current_url'])
            ? esc_url_raw(wp_unslash($_POST['current_url']))
            : null;
        // phpcs:enable

        $redirect_url = $this->assets->get_redirect_url($current_url);

        // User is not logged in, return popup HTML and data
        wp_send_json_success([
            'show' => true,
            'html' => $this->get_popup_html($redirect_url),
            'nonce' => wp_create_nonce('show_login_nonce'),
            'redirectUrl' => $redirect_url,
            'suppressLoading' => $suppress_loading
        ]);
    }

    /**
     * Get popup HTML
     *
     * @param string $redirect_url Where to send the user after login (used by the template).
     * @return string Popup HTML.
     */
    private function get_popup_html(string $redirect_url): string {
        $template_path = $this->plugin_path . 'templates/popup.php';

        if (!file_exists($template_path)) {
            return '';
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}
