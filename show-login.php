<?php
/**
 * Plugin Name: Show Login
 * Plugin URI: https://github.com/caseproof/show-login
 * Description: A lightweight front-end login popup triggered by ?sl=true URL parameter
 * Version: 1.0.0
 * Author: Caseproof
 * Author URI: https://caseproof.com
 * Text Domain: show-login
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Show Login Class
 */
class Show_Login {
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Show_Login
     */
    public static function get_instance(): Show_Login {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Load plugin text domain for translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Only load on front-end for non-logged-in users with ?sl=true parameter
        if (!is_admin() && !is_user_logged_in() && $this->should_show_popup()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_footer', [$this, 'render_popup_html']);
        }

        // Register AJAX handlers (works for both logged-in and logged-out users)
        add_action('wp_ajax_nopriv_show_login_authenticate', [$this, 'handle_login_ajax']);
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain(): void {
        load_plugin_textdomain('show-login', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Check if popup should be shown based on URL parameter
     *
     * @return bool
     */
    private function should_show_popup(): bool {
        // Check for ?sl=true or &sl=true in the URL
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation, no state change
        return isset($_GET['sl']) && sanitize_text_field(wp_unslash($_GET['sl'])) === 'true';
    }

    /**
     * Enqueue plugin assets
     */
    public function enqueue_assets(): void {
        // Enqueue inline CSS
        wp_register_style('show-login-css', false);
        wp_enqueue_style('show-login-css');
        wp_add_inline_style('show-login-css', $this->get_inline_css());

        // Enqueue inline JavaScript
        wp_register_script('show-login-js', false, [], self::VERSION, true);
        wp_enqueue_script('show-login-js');
        wp_add_inline_script('show-login-js', $this->get_inline_js());

        // Localize script with AJAX URL and nonce
        wp_localize_script('show-login-js', 'showLoginData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('show_login_nonce'),
            'redirectUrl' => $this->get_redirect_url(),
        ]);
    }

    /**
     * Get redirect URL (current URL without sl parameter)
     *
     * @return string
     */
    private function get_redirect_url(): string {
        // Use WordPress functions to get the current URL properly
        // This handles all edge cases and proper escaping
        $current_url = '';

        if (isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            $protocol = is_ssl() ? 'https://' : 'http://';
            // Use wp_unslash but don't use sanitize_text_field as it strips valid URI characters
            $http_host = wp_unslash($_SERVER['HTTP_HOST']);
            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);

            // Build URL and sanitize with esc_url_raw which preserves URI structure
            $current_url = esc_url_raw($protocol . $http_host . $request_uri);
        }

        // Remove sl parameter from URL - this preserves all other query parameters
        $redirect_url = remove_query_arg('sl', $current_url);

        /**
         * Filter the redirect URL after successful login.
         *
         * @param string $redirect_url The URL to redirect to after login.
         * @param string $current_url  The current page URL before removing sl parameter.
         */
        return apply_filters('show_login_redirect_url', $redirect_url, $current_url);
    }

    /**
     * Get inline CSS for popup
     *
     * @return string
     */
    private function get_inline_css(): string {
        return <<<CSS
        /* Show Login Popup Styles */
        #show-login-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 999999;
            justify-content: center;
            align-items: center;
        }

        #show-login-overlay.show-login-active {
            display: flex;
        }

        #show-login-popup {
            position: relative;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            padding: 30px;
            box-sizing: border-box;
        }

        #show-login-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: none;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #show-login-close:hover {
            color: #000;
        }

        #show-login-popup h2 {
            margin: 0 0 20px 0;
            font-size: 24px;
            color: #333;
        }

        .show-login-field {
            margin-bottom: 15px;
        }

        .show-login-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .show-login-field input[type="text"],
        .show-login-field input[type="email"],
        .show-login-field input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }

        .show-login-field input:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
        }

        #show-login-error {
            display: none;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        #show-login-error.show-login-visible {
            display: block;
        }

        .show-login-submit-wrapper {
            margin-top: 20px;
        }

        #show-login-submit {
            width: 100%;
            padding: 12px;
            background: #0073aa;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        #show-login-submit:hover {
            background: #005a87;
        }

        #show-login-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        #show-login-submit.show-login-loading {
            position: relative;
            color: transparent;
        }

        #show-login-submit.show-login-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: show-login-spin 0.6s linear infinite;
        }

        @keyframes show-login-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .show-login-remember {
            margin: 15px 0;
        }

        .show-login-remember label {
            display: flex;
            align-items: center;
            font-weight: normal;
            cursor: pointer;
            font-size: 14px;
        }

        .show-login-remember input[type="checkbox"] {
            margin-right: 8px;
        }
CSS;
    }

    /**
     * Get inline JavaScript for popup functionality
     *
     * @return string
     */
    private function get_inline_js(): string {
        return <<<'JS'
        (function() {
            'use strict';

            // Show popup on page load
            document.addEventListener('DOMContentLoaded', function() {
                const overlay = document.getElementById('show-login-overlay');
                if (overlay) {
                    overlay.classList.add('show-login-active');
                }
            });

            // Close popup functionality
            const closeBtn = document.getElementById('show-login-close');
            const overlay = document.getElementById('show-login-overlay');

            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closePopup();
                });
            }

            // Close on overlay click (not popup content)
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closePopup();
                    }
                });
            }

            // Close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePopup();
                }
            });

            function closePopup() {
                if (overlay) {
                    overlay.classList.remove('show-login-active');
                }
            }

            // Handle form submission
            const form = document.getElementById('show-login-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleLogin();
                });
            }

            function handleLogin() {
                const username = document.getElementById('show-login-username').value;
                const password = document.getElementById('show-login-password').value;
                const remember = document.getElementById('show-login-remember').checked;
                const submitBtn = document.getElementById('show-login-submit');
                const errorDiv = document.getElementById('show-login-error');

                // Clear previous errors
                errorDiv.classList.remove('show-login-visible');
                errorDiv.textContent = '';

                // Validation
                if (!username || !password) {
                    showError('Please enter both username and password.');
                    return;
                }

                // Disable submit button and show loading state
                submitBtn.disabled = true;
                submitBtn.classList.add('show-login-loading');

                // Prepare form data
                const formData = new FormData();
                formData.append('action', 'show_login_authenticate');
                formData.append('nonce', showLoginData.nonce);
                formData.append('username', username);
                formData.append('password', password);
                formData.append('remember', remember ? '1' : '0');

                // Send AJAX request
                fetch(showLoginData.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Successful login - reload page
                        window.location.href = showLoginData.redirectUrl;
                    } else {
                        // Show error message
                        showError(data.data.message || 'Login failed. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('show-login-loading');
                    }
                })
                .catch(error => {
                    showError('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('show-login-loading');
                });
            }

            function showError(message) {
                const errorDiv = document.getElementById('show-login-error');
                errorDiv.textContent = message;
                errorDiv.classList.add('show-login-visible');
            }
        })();
JS;
    }

    /**
     * Render popup HTML in footer
     */
    public function render_popup_html(): void {
        ?>
        <div id="show-login-overlay">
            <div id="show-login-popup">
                <button id="show-login-close" type="button" aria-label="Close">&times;</button>
                <h2><?php echo esc_html(apply_filters('show_login_popup_title', __('Log In', 'show-login'))); ?></h2>

                <div id="show-login-error" role="alert"></div>

                <form id="show-login-form" method="post" novalidate>
                    <?php
                    /**
                     * Fires at the beginning of the login form.
                     * Allows other plugins to add fields or modify the form.
                     */
                    do_action('show_login_form_start');
                    ?>

                    <div class="show-login-field">
                        <label for="show-login-username">
                            <?php echo esc_html(apply_filters('show_login_username_label', __('Username or Email', 'show-login'))); ?>
                        </label>
                        <input
                            type="text"
                            id="show-login-username"
                            name="username"
                            autocomplete="username"
                            required
                            aria-required="true"
                        />
                    </div>

                    <div class="show-login-field">
                        <label for="show-login-password">
                            <?php echo esc_html(apply_filters('show_login_password_label', __('Password', 'show-login'))); ?>
                        </label>
                        <input
                            type="password"
                            id="show-login-password"
                            name="password"
                            autocomplete="current-password"
                            required
                            aria-required="true"
                        />
                    </div>

                    <div class="show-login-remember">
                        <label>
                            <input
                                type="checkbox"
                                id="show-login-remember"
                                name="remember"
                                value="1"
                            />
                            <?php echo esc_html(apply_filters('show_login_remember_label', __('Remember Me', 'show-login'))); ?>
                        </label>
                    </div>

                    <?php
                    /**
                     * Fires in the middle of the login form, before the submit button.
                     * Allows other plugins to add additional fields (e.g., 2FA fields).
                     */
                    do_action('show_login_form_middle');
                    ?>

                    <div class="show-login-submit-wrapper">
                        <button type="submit" id="show-login-submit">
                            <?php echo esc_html(apply_filters('show_login_submit_label', __('Log In', 'show-login'))); ?>
                        </button>
                    </div>

                    <?php
                    /**
                     * Fires at the end of the login form.
                     * Allows other plugins to add links or additional content.
                     */
                    do_action('show_login_form_end');
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX login request
     */
    public function handle_login_ajax(): void {
        // Check rate limiting before processing
        if ($this->is_rate_limited()) {
            wp_send_json_error([
                'message' => __('Too many login attempts. Please try again later.', 'show-login')
            ], 429);
        }

        // Verify nonce using WordPress standard function
        check_ajax_referer('show_login_nonce', 'nonce', true);

        // Get credentials from POST
        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
        $remember = isset($_POST['remember']) && sanitize_text_field(wp_unslash($_POST['remember'])) === '1';

        // Validate inputs
        if (empty($username) || empty($password)) {
            wp_send_json_error([
                'message' => __('Please enter both username and password.', 'show-login')
            ]);
        }

        // Prepare credentials array
        $credentials = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        /**
         * Filter credentials before authentication.
         * Allows other plugins to modify credentials or add additional data.
         *
         * @param array $credentials The credentials array.
         */
        $credentials = apply_filters('show_login_credentials', $credentials);

        /**
         * Fires before authentication attempt.
         * Allows other plugins to perform pre-authentication checks.
         *
         * @param string $username The username being authenticated.
         */
        do_action('show_login_before_authenticate', $username);

        // Attempt authentication using WordPress core function
        $user = wp_signon($credentials, is_ssl());

        /**
         * Fires after authentication attempt.
         * Allows other plugins to perform post-authentication actions.
         *
         * @param WP_User|WP_Error $user     The user object or error.
         * @param array            $credentials The credentials used.
         */
        do_action('show_login_after_authenticate', $user, $credentials);

        // Check for errors
        if (is_wp_error($user)) {
            // Log failed attempt for rate limiting
            $this->log_failed_attempt();

            /**
             * Filter error message before sending to client.
             *
             * @param string   $message The error message.
             * @param WP_Error $user    The error object.
             */
            $error_message = apply_filters(
                'show_login_error_message',
                $user->get_error_message(),
                $user
            );

            wp_send_json_error([
                'message' => $error_message
            ]);
        }

        // Clear failed attempts on successful login
        $this->clear_failed_attempts();

        /**
         * Fires on successful login.
         * Allows other plugins to perform actions after successful login.
         *
         * @param WP_User $user The user object.
         */
        do_action('show_login_success', $user);

        // Success response
        wp_send_json_success([
            'message' => __('Login successful! Redirecting...', 'show-login'),
            'user_id' => $user->ID,
        ]);
    }

    /**
     * Check if current IP is rate limited
     *
     * @return bool
     */
    private function is_rate_limited(): bool {
        $ip = $this->get_client_ip();
        $transient_key = 'show_login_attempts_' . md5($ip);
        $attempts = get_transient($transient_key);

        /**
         * Filter the maximum number of login attempts allowed.
         *
         * @param int $max_attempts Maximum attempts allowed (default: 5).
         */
        $max_attempts = apply_filters('show_login_max_attempts', 5);

        return $attempts && (int) $attempts >= $max_attempts;
    }

    /**
     * Log a failed login attempt
     */
    private function log_failed_attempt(): void {
        $ip = $this->get_client_ip();
        $transient_key = 'show_login_attempts_' . md5($ip);
        $attempts = get_transient($transient_key);

        $attempts = $attempts ? (int) $attempts + 1 : 1;

        /**
         * Filter the rate limit window in seconds.
         *
         * @param int $window Time window in seconds (default: 900 = 15 minutes).
         */
        $window = apply_filters('show_login_rate_limit_window', 900);

        set_transient($transient_key, $attempts, $window);
    }

    /**
     * Clear failed login attempts for current IP
     */
    private function clear_failed_attempts(): void {
        $ip = $this->get_client_ip();
        $transient_key = 'show_login_attempts_' . md5($ip);
        delete_transient($transient_key);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string {
        $ip = '';

        // Check for proxy headers in order of reliability
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // If X-Forwarded-For contains multiple IPs, take the first one
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    break;
                }
            }
        }

        /**
         * Filter the detected client IP address.
         *
         * @param string $ip The detected IP address.
         */
        return apply_filters('show_login_client_ip', $ip);
    }
}

// Initialize the plugin
Show_Login::get_instance();
