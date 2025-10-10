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
        // Ensure users can see the status message (prevents flash if AJAX is too fast)
        sleep(1);

        // Check if user is already logged in
        if (is_user_logged_in()) {
            wp_send_json_success([
                'show' => false,
                'reason' => 'already_logged_in'
            ]);
        }

        // User is not logged in, return popup HTML and data
        wp_send_json_success([
            'show' => true,
            'html' => $this->get_popup_html(),
            'nonce' => wp_create_nonce('show_login_nonce'),
            'redirectUrl' => $this->assets->get_redirect_url()
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
