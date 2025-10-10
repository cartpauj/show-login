<?php
/**
 * Rate Limiter
 *
 * Handles IP-based rate limiting to prevent brute-force attacks.
 *
 * @package ShowLogin
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Rate_Limiter class
 */
class Show_Login_Rate_Limiter {
    /**
     * Check if current IP is rate limited
     *
     * @return array Array with 'is_limited' boolean and 'time_remaining' in seconds.
     */
    public function is_rate_limited(): array {
        $ip = $this->get_client_ip();
        $transient_key = 'show_login_attempts_' . md5($ip);
        $transient_timeout_key = '_transient_timeout_' . $transient_key;

        $attempts = get_transient($transient_key);

        /**
         * Filter the maximum number of login attempts allowed.
         *
         * @since 1.0.0
         * @param int $max_attempts Maximum attempts allowed (default: 5).
         */
        $max_attempts = apply_filters('show_login_max_attempts', 5);

        $is_limited = $attempts && (int) $attempts >= $max_attempts;

        // Calculate time remaining if limited
        $time_remaining = 0;
        if ($is_limited) {
            global $wpdb;
            $timeout = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
                    $transient_timeout_key
                )
            );

            if ($timeout) {
                $time_remaining = (int) $timeout - time();
                if ($time_remaining < 0) {
                    $time_remaining = 0;
                }
            }
        }

        return [
            'is_limited' => $is_limited,
            'time_remaining' => $time_remaining,
        ];
    }

    /**
     * Log a failed login attempt
     */
    public function log_failed_attempt(): void {
        $ip = $this->get_client_ip();
        $transient_key = 'show_login_attempts_' . md5($ip);
        $attempts = get_transient($transient_key);

        $attempts = $attempts ? (int) $attempts + 1 : 1;

        /**
         * Filter the rate limit window in seconds.
         *
         * @since 1.0.0
         * @param int $window Time window in seconds (default: 60 = 1 minute).
         */
        $window = apply_filters('show_login_rate_limit_window', 60);

        set_transient($transient_key, $attempts, $window);
    }

    /**
     * Clear failed login attempts for current IP
     */
    public function clear_failed_attempts(): void {
        $ip = $this->get_client_ip();
        $transient_key = 'show_login_attempts_' . md5($ip);
        delete_transient($transient_key);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address.
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
         * @since 1.0.0
         * @param string $ip The detected IP address.
         */
        return apply_filters('show_login_client_ip', $ip);
    }
}
