<?php
/**
 * Login Popup Template
 *
 * This template is loaded in the footer for non-logged-in users
 * when the ?sl=true parameter is present.
 *
 * @package ShowLogin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="show-login-overlay">
    <div id="show-login-popup">
        <button id="show-login-close" type="button" aria-label="<?php esc_attr_e('Close', 'show-login'); ?>">&times;</button>

        <h2><?php echo esc_html(apply_filters('show_login_popup_title', __('Log In', 'show-login'))); ?></h2>

        <?php
        /**
         * Fires after the popup title, before the error div.
         * Perfect location for adding a logo or branding.
         *
         * @since 1.0.0
         */
        do_action('show_login_after_title');
        ?>

        <div id="show-login-error" role="alert"></div>

        <form id="show-login-form" method="post" novalidate>
            <?php
            /**
             * Fires at the beginning of the login form.
             * Allows other plugins to add fields or modify the form.
             *
             * @since 1.0.0
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
             *
             * @since 1.0.0
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
             *
             * @since 1.0.0
             */
            do_action('show_login_form_end');
            ?>
        </form>
    </div>
</div>
