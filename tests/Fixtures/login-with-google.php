<?php

/**
 * Minimal stand-in for rtCamp's Login with Google plugin.
 *
 * Mirrors the parts Show Login uses: RtCamp\GoogleLogin\plugin()->container()->get(...)
 * with the "settings", "shortcode" and "assets" services. Tests drive it through
 * Show_Login_Test_Google.
 *
 * @package ShowLogin\Tests
 */

// phpcs:disable

namespace {
    /**
     * Like the real Settings module: values come from __get() and there is
     * no __isset(), so isset()/empty() on them are always false/true.
     */
    class Show_Login_Test_Google_Settings {
        private $values;

        public function __construct(string $client_id = '', string $client_secret = '') {
            $this->values = ['client_id' => $client_id, 'client_secret' => $client_secret];
        }

        public function __get(string $name) {
            return $this->values[$name] ?? null;
        }
    }

    class Show_Login_Test_Google {
        /** @var Show_Login_Test_Google_Settings Settings service. */
        public static $settings;

        /** @var array[] Attributes the shortcode callback was called with. */
        public static $shortcode_calls = [];

        /** @var bool Whether container()->get() throws, like an unknown service. */
        public static $container_throws = false;

        /** @var bool Whether the shortcode module is missing (removed via rtcamp.google_login_modules). */
        public static $shortcode_missing = false;

        public static function reset(): void {
            self::$settings         = new Show_Login_Test_Google_Settings();
            self::$shortcode_calls  = [];
            self::$container_throws = false;
            self::$shortcode_missing = false;
        }
    }
    Show_Login_Test_Google::reset();
}

namespace RtCamp\GoogleLogin {
    class Test_Container {
        public function get(string $id) {
            if (\Show_Login_Test_Google::$container_throws) {
                throw new \InvalidArgumentException("Unknown service {$id}");
            }

            switch ($id) {
                case 'settings':
                    return \Show_Login_Test_Google::$settings;
                case 'shortcode':
                    if (\Show_Login_Test_Google::$shortcode_missing) {
                        break;
                    }
                    return new Test_Shortcode();
                case 'assets':
                    return new Test_Assets();
            }

            throw new \InvalidArgumentException("Unknown service {$id}");
        }
    }

    class Test_Shortcode {
        // Same defaults and output shape as the real [google_login] callback.
        public function callback($attrs = []): string {
            \Show_Login_Test_Google::$shortcode_calls[] = $attrs;
            $attrs = shortcode_atts(['button_text' => 'Login with google', 'redirect_to' => admin_url()], $attrs);
            $state = base64_encode(wp_json_encode(['redirect_to' => $attrs['redirect_to'], 'provider' => 'google']));

            return '<div class="wp_google_login"><div class="wp_google_login__button-container">'
                . '<a class="wp_google_login__button" href="' . esc_url('https://accounts.google.com/o/oauth2/auth?state=' . $state) . '">'
                . '<span class="wp_google_login__google-icon"></span>' . esc_html($attrs['button_text']) . '</a></div></div>';
        }
    }

    class Test_Assets {
        public function register_login_styles(): void {
            wp_register_style('login-with-google', 'https://example.org/wp-content/plugins/login-with-google/assets/build/css/button/style.css');
        }
    }

    class Test_Plugin {
        public function container(): Test_Container {
            return new Test_Container();
        }
    }

    function plugin(): Test_Plugin {
        return new Test_Plugin();
    }
}
