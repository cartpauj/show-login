<?php

/**
 * Caseproof Social Login Integration
 *
 * Adds the social login buttons from the Caseproof social login add-ons
 * (MemberPress and WishList Member Social Login) to the popup.
 *
 * @package ShowLogin
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Show_Login_Social_Login class
 */
class Show_Login_Social_Login {
    /**
     * Functions that return each add-on's social login handler, in priority order.
     * Each add-on bundles its own (prefixed) copy of caseproof/social-login.
     *
     * @var string[]
     */
    private const HANDLER_FUNCTIONS = [
        'mpslHandlerInstance',   // MemberPress Social Login
        'wlmslHandlerInstance',  // WishList Member Social Login
    ];

    /**
     * Initialize Social Login integration
     */
    public static function init(): void {
        add_action('show_login_social_buttons', [__CLASS__, 'render_buttons']);

        // After the add-on registers its stylesheet (priority 10).
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles'], 20);
    }

    /**
     * Get the enabled and verified providers of the first active add-on
     *
     * @return array Provider objects (empty when social login isn't available).
     */
    public static function get_providers(): array {
        foreach (self::HANDLER_FUNCTIONS as $function) {
            if (!function_exists($function)) {
                continue;
            }

            $handler = $function();
            if (!is_object($handler) || empty($handler->oauthProviders) || !is_array($handler->oauthProviders)) {
                continue;
            }

            $providers = array_values(array_filter($handler->oauthProviders, static function ($provider) {
                return !empty($provider->isEnabled) && !empty($provider->isVerified);
            }));

            if ($providers) {
                return $providers;
            }
        }

        return [];
    }

    /**
     * Check if any social login provider is available
     *
     * @return bool
     */
    public static function is_social_login_active(): bool {
        return self::get_providers() !== [];
    }

    /**
     * Enqueue the add-on's button styles
     *
     * The popup is injected after page load, so its styles must already be on the page.
     */
    public static function enqueue_styles(): void {
        if (is_user_logged_in() || !self::is_social_login_active()) {
            return;
        }

        wp_enqueue_style('cspfsl-buttons-style');
    }

    /**
     * Output the social login buttons
     *
     * @param string $redirect_url Where to send the user after they log in.
     */
    public static function render_buttons(string $redirect_url): void {
        foreach (self::get_providers() as $provider) {
            $id = sanitize_html_class($provider->id);

            // The add-on can't work out the current page during an ajax request
            // (it sends an empty "redir"), so set the return URL ourselves.
            $url = add_query_arg(
                'redir',
                rawurlencode(base64_encode($redirect_url)), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The add-on expects base64.
                $provider->getSocialLoginUrl($provider->id, 'wp_login')
            );

            /* translators: %s: login provider name, e.g. Google */
            $text = sprintf(__('Log in with %s', 'show-login'), $provider->getName());

            printf(
                '<span class="cspf-shortcode-login-button"><a class="cspfsl-social-login-btn" href="%1$s"><span class="%2$s-login-btn"><span class="icon"></span><span class="text %2$s-login-span-text">%3$s</span></span></a></span>',
                esc_url($url),
                esc_attr($id),
                esc_html($text)
            );
        }
    }
}

// Initialize Social Login integration
add_action('plugins_loaded', [Show_Login_Social_Login::class, 'init'], 20);
