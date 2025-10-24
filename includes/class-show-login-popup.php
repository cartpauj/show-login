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

        // Only add delay if loading state is being shown (prevents flash if AJAX is too fast)
        if (!$suppress_loading) {
            sleep(1);
        }

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
        $redirect_url = !empty($_POST['current_url'])
            ? sanitize_text_field(wp_unslash($_POST['current_url']))
            : $this->assets->get_redirect_url();

        // Remove popup trigger parameters from redirect URL
        $redirect_url = remove_query_arg(['sl', 'show_login'], $redirect_url);

        // User is not logged in, return popup HTML and data
        wp_send_json_success([
            'show' => true,
            'html' => $this->get_popup_html(),
            'nonce' => wp_create_nonce('show_login_nonce'),
            'redirectUrl' => $redirect_url,
            'suppressLoading' => $suppress_loading
        ]);
    }

    /**
     * Get popup HTML
     *
     * @return string Popup HTML.
     */
    private function get_popup_html(): string {
        $template_path = $this->plugin_path . 'templates/popup.php';

        if (!file_exists($template_path)) {
            return '';
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}
