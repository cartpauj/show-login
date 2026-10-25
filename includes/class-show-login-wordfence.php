<?php

/**
 * Wordfence Login Security Integration
 *
 * Lets users with Wordfence two-factor authentication finish logging in from
 * the popup.
 *
 * @package ShowLogin
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Wordfence class
 *
 * Wordfence checks the code during wp_signon(): with no code it fails the
 * login with `wfls_twofactor_required`, and it reads the code from the
 * `wfls-token` POST field. So the popup shows a code field when that error
 * comes back, and sends the login again with the code in `wfls-token`.
 */
class Show_Login_Wordfence {
    /**
     * Error code Wordfence returns when the password was right but a 2FA code is needed
     */
    public const CODE_REQUIRED = 'wfls_twofactor_required';

    /**
     * Check if Wordfence Login Security is active
     *
     * @return bool
     */
    public static function is_wordfence_active(): bool {
        return class_exists('\WordfenceLS\Controller_WordfenceLS');
    }

    /**
     * Initialize Wordfence integration
     */
    public static function init(): void {
        if (!self::is_wordfence_active()) {
            return;
        }

        add_action('show_login_form_middle', [__CLASS__, 'render_code_field']);
    }

    /**
     * Whether a login error means the user needs to enter their 2FA code
     *
     * Wordfence only returns this after the password checked out, so it
     * doesn't reveal anything about which accounts exist.
     *
     * @param WP_Error $error The login error.
     * @return bool
     */
    public static function is_code_required(WP_Error $error): bool {
        return self::is_wordfence_active() && $error->get_error_code() === self::CODE_REQUIRED;
    }

    /**
     * Output the 2FA code field, hidden until Wordfence asks for a code
     *
     * It's disabled while hidden so it isn't sent with normal logins.
     */
    public static function render_code_field(): void {
        ?>
        <div class="show-login-field" id="show-login-2fa-field" hidden>
            <label for="show-login-2fa-code">
                <?php echo esc_html(apply_filters('show_login_2fa_code_label', __('Two-Factor Code', 'show-login'))); ?>
            </label>
            <input
                type="text"
                id="show-login-2fa-code"
                name="wfls-token"
                autocomplete="one-time-code"
                disabled
            />
        </div>
        <?php
    }
}

// Initialize Wordfence integration
add_action('plugins_loaded', [Show_Login_Wordfence::class, 'init'], 20);
