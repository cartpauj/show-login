<?php
/**
 * Assets Handler
 *
 * Handles enqueuing of CSS and JavaScript files.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Assets class
 */
class Show_Login_Assets {
    /**
     * Plugin version
     *
     * @var string
     */
    private string $version;

    /**
     * Plugin directory URL
     *
     * @var string
     */
    private string $plugin_url;

    /**
     * Constructor
     *
     * @param string $version Plugin version.
     * @param string $plugin_url Plugin directory URL.
     */
    public function __construct(string $version, string $plugin_url) {
        $this->version = $version;
        $this->plugin_url = $plugin_url;
    }

    /**
     * Enqueue plugin assets
     */
    public function enqueue_assets(): void {
        // Enqueue CSS
        wp_enqueue_style(
            'show-login',
            $this->plugin_url . 'assets/css/show-login.css',
            [],
            $this->version
        );

        // Add dynamic button color styles
        $button_styles = $this->get_button_styles();
        wp_add_inline_style('show-login', $button_styles);

        // Enqueue JavaScript
        wp_enqueue_script(
            'show-login',
            $this->plugin_url . 'assets/js/show-login.js',
            [],
            $this->version,
            true
        );

        /**
         * Filter to suppress loading spinner and status messages.
         * If true, popup stays hidden until AJAX confirms user is logged out.
         *
         * @since 1.0.0
         * @param bool $suppress Whether to suppress loading state (default: false).
         */
        $suppress_loading = apply_filters('show_login_suppress_loading_state', false);

        // Localize script with AJAX URL and suppress flag
        // Nonce and redirectUrl are fetched dynamically via AJAX (cache-compatible)
        wp_localize_script('show-login', 'showLoginData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'suppressLoading' => $suppress_loading,
        ]);
    }

    /**
     * Get dynamic button styles based on filters
     *
     * @return string CSS for button colors.
     */
    private function get_button_styles(): string {
        /**
         * Filter the submit button background color.
         *
         * @since 1.0.0
         * @param string $color The background color (default: #0073aa).
         */
        $button_bg = apply_filters('show_login_button_bg_color', '#0073aa');

        /**
         * Filter the submit button hover background color.
         *
         * @since 1.0.0
         * @param string $color The hover background color (default: #005a87).
         */
        $button_hover_bg = apply_filters('show_login_button_hover_bg_color', '#005a87');

        /**
         * Filter the submit button text color.
         *
         * @since 1.0.0
         * @param string $color The text color (default: #fff).
         */
        $button_text_color = apply_filters('show_login_button_text_color', '#fff');

        // Sanitize color values
        $button_bg = sanitize_hex_color($button_bg);
        $button_hover_bg = sanitize_hex_color($button_hover_bg);
        $button_text_color = sanitize_hex_color($button_text_color);

        return sprintf(
            '#show-login-submit { background: %s; color: %s; } #show-login-submit:hover { background: %s; }',
            esc_attr($button_bg),
            esc_attr($button_text_color),
            esc_attr($button_hover_bg)
        );
    }

    /**
     * Get redirect URL (current URL without sl parameter)
     *
     * @return string Redirect URL.
     */
    public function get_redirect_url(): string {
        $current_url = '';

        if (isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            $protocol = is_ssl() ? 'https://' : 'http://';
            $http_host = wp_unslash($_SERVER['HTTP_HOST']);
            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
            $current_url = esc_url_raw($protocol . $http_host . $request_uri);
        }

        // Remove all popup trigger parameters from URL
        $redirect_url = remove_query_arg(['sl', 'show_login'], $current_url);

        /**
         * Filter the redirect URL after successful login.
         *
         * @since 1.0.0
         * @param string $redirect_url The URL to redirect to after login.
         * @param string $current_url  The current page URL before removing sl parameter.
         */
        $redirect_url = apply_filters('show_login_redirect_url', $redirect_url, $current_url);

        // Validate redirect URL to prevent open redirects (must be internal)
        return wp_validate_redirect($redirect_url, home_url());
    }
}
