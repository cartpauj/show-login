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
        $record = $this->get_record();

        /**
         * Filter the maximum number of login attempts allowed.
         *
         * @since 1.0.0
         * @param int $max_attempts Maximum attempts allowed (default: 5).
         */
        $max_attempts = apply_filters('show_login_max_attempts', 5);

        $is_limited = $record['count'] > 0 && $record['count'] >= $max_attempts;

        return [
            'is_limited' => $is_limited,
            'time_remaining' => $is_limited ? max(0, $record['expires'] - time()) : 0,
        ];
    }

    /**
     * Log a failed login attempt
     */
    public function log_failed_attempt(): void {
        $record = $this->get_record();

        /**
         * Filter the rate limit window in seconds.
         *
         * @since 1.0.0
         * @param int $window Time window in seconds (default: 60 = 1 minute).
         */
        $window = (int) apply_filters('show_login_rate_limit_window', 60);

        // The expiry is stored with the count because transients have no API for
        // "when does this expire?" (and it can't be read from wp_options on sites
        // with a persistent object cache).
        set_transient($this->get_transient_key(), [
            'count' => $record['count'] + 1,
            'expires' => time() + $window,
        ], $window);
    }

    /**
     * Clear failed login attempts for current IP
     */
    public function clear_failed_attempts(): void {
        delete_transient($this->get_transient_key());
    }

    /**
     * Get the stored attempt record for the current IP
     *
     * @return array{count: int, expires: int}
     */
    private function get_record(): array {
        $value = get_transient($this->get_transient_key());

        if (is_array($value)) {
            return [
                'count' => (int) ($value['count'] ?? 0),
                'expires' => (int) ($value['expires'] ?? 0),
            ];
        }

        // Plain count saved by 1.0.0; its expiry is unknown.
        return ['count' => (int) $value, 'expires' => 0];
    }

    /**
     * Get the transient key for the current IP
     *
     * @return string
     */
    private function get_transient_key(): string {
        return 'show_login_attempts_' . md5($this->get_client_ip());
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
