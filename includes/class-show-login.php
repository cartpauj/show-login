<?php
/**
 * Main Plugin Class
 *
 * Coordinates all plugin functionality and initializes components.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login class
 */
class Show_Login {
    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Singleton instance
     *
     * @var Show_Login|null
     */
    private static ?Show_Login $instance = null;

    /**
     * Plugin directory path
     *
     * @var string
     */
    private string $plugin_path;

    /**
     * Plugin directory URL
     *
     * @var string
     */
    private string $plugin_url;

    /**
     * Assets handler
     *
     * @var Show_Login_Assets
     */
    private Show_Login_Assets $assets;

    /**
     * Rate limiter
     *
     * @var Show_Login_Rate_Limiter
     */
    private Show_Login_Rate_Limiter $rate_limiter;

    /**
     * Authenticator
     *
     * @var Show_Login_Authenticator
     */
    private Show_Login_Authenticator $authenticator;

    /**
     * Get singleton instance
     *
     * @param string $plugin_file Main plugin file path.
     * @return Show_Login
     */
    public static function get_instance(string $plugin_file = ''): Show_Login {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param string $plugin_file Main plugin file path.
     */
    private function __construct(string $plugin_file) {
        $this->plugin_path = plugin_dir_path($plugin_file);
        $this->plugin_url = plugin_dir_url($plugin_file);

        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        require_once $this->plugin_path . 'includes/class-show-login-assets.php';
        require_once $this->plugin_path . 'includes/class-show-login-rate-limiter.php';
        require_once $this->plugin_path . 'includes/class-show-login-authenticator.php';
    }

    /**
     * Initialize components
     */
    private function init_components(): void {
        $this->assets = new Show_Login_Assets(self::VERSION, $this->plugin_url);
        $this->rate_limiter = new Show_Login_Rate_Limiter();
        $this->authenticator = new Show_Login_Authenticator($this->rate_limiter);
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Load plugin text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Register AJAX handler
        add_action('wp_ajax_nopriv_show_login_authenticate', [$this->authenticator, 'handle_login_ajax']);

        // Delay conditional logic until WordPress is fully loaded
        add_action('wp', [$this, 'setup_frontend_hooks']);
    }

    /**
     * Setup front-end hooks after WordPress is loaded
     */
    public function setup_frontend_hooks(): void {
        // Only load on front-end for non-logged-in users with ?sl=true parameter
        if (!is_admin() && !is_user_logged_in() && $this->should_show_popup()) {
            add_action('wp_enqueue_scripts', [$this->assets, 'enqueue_assets']);
            add_action('wp_footer', [$this, 'render_popup']);
        }
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'show-login',
            false,
            dirname(plugin_basename($this->plugin_path)) . '/languages'
        );
    }

    /**
     * Check if popup should be shown based on URL parameter
     *
     * @return bool
     */
    private function should_show_popup(): bool {
        // Check for ?sl=true or &sl=true in the URL
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation
        return isset($_GET['sl']) && sanitize_text_field(wp_unslash($_GET['sl'])) === 'true';
    }

    /**
     * Render popup template in footer
     */
    public function render_popup(): void {
        $template_path = $this->plugin_path . 'templates/popup.php';

        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public function get_plugin_path(): string {
        return $this->plugin_path;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function get_plugin_url(): string {
        return $this->plugin_url;
    }
}
