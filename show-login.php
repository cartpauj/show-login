<?php

/**
 * Plugin Name: Show Login
 * Plugin URI: https://github.com/cartpauj/show-login
 * Description: A lightweight front-end login popup triggered by ?sl=true, ?sl=1, ?show_login=true, or ?show_login=1 URL parameters
 * Version: 1.1.0
 * Author: cartpauj
 * Author URI: https://github.com/cartpauj
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

// Define plugin constants
define('SHOW_LOGIN_VERSION', '1.1.0');
define('SHOW_LOGIN_FILE', __FILE__);
define('SHOW_LOGIN_PATH', plugin_dir_path(__FILE__));
define('SHOW_LOGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin initialization
 */
function show_login_init() {
    // Load main class
    require_once SHOW_LOGIN_PATH . 'includes/class-show-login.php';

    // Initialize plugin
    Show_Login::get_instance(__FILE__);
}

add_action('plugins_loaded', 'show_login_init', 1);
