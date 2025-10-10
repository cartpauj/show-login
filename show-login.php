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

// Define plugin constants
define('SHOW_LOGIN_VERSION', '1.0.0');
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
