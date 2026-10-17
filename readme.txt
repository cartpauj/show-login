=== Show Login ===
Contributors: cartpauj
Tags: login, popup, ajax, frontend, modal
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight front-end login popup triggered by a URL parameter. Perfect for seamless login experiences without leaving the page.

== Description ==

Show Login adds a beautiful, accessible login popup to your WordPress site that appears when you add a special parameter to any URL. Perfect for membership sites, marketing campaigns, or anywhere you want users to log in without leaving their current page.

= Key Features =

* **Lightweight & Fast** - Clean, organized code structure with minimal footprint
* **Pure JavaScript** - No jQuery or other libraries needed
* **Cache Compatible** - Works perfectly with all page caching solutions
* **Seamless Experience** - Users log in without leaving the page
* **Instant Feedback** - Loading spinner appears immediately, smooth transitions
* **Secure by Default** - Nonce checks, safe redirects, and no username fishing. Works with your security plugin for login limits
* **Fully Accessible** - WCAG compliant with proper ARIA attributes
* **Translation Ready** - Every message can be translated, and it works with Loco Translate out of the box
* **Developer Friendly** - Extensive hooks for customization
* **Social Login** - Shows MemberPress / WishList Member Social Login and Login with Google buttons in the popup
* **Well Architected** - Proper separation of concerns with dedicated classes

= How It Works =

Simply add `?sl=true` to any URL on your site:

* `https://yoursite.com/page?sl=true`
* `https://yoursite.com/shop?sl=true`
* `https://yoursite.com/post?id=123&sl=true`

When a user visits the URL, they'll see an instant loading popup that checks their login status. Logged-out users see the login form, while logged-in users see a friendly message before the popup closes. After logging in, users stay on the same page - no redirects to wp-login.php needed!

= Perfect For =

* Membership sites requiring login
* Protected content pages
* Marketing campaigns with login links
* Email newsletters with direct login access
* Social media links requiring authentication
* Seamless user experiences

= Security Features =

* **Nonce Verification** - All requests are validated
* **Proper Sanitization** - All inputs are cleaned and validated
* **WordPress Authentication** - Uses core WordPress login functions
* **Cache Compatible** - Login status checked via AJAX, never cached
* **Username Enumeration Protection** - Generic error messages prevent user discovery
* **Works With Security Plugins** - Wordfence, Limit Login Attempts Reloaded, Solid Security and similar plugins limit popup logins just like wp-login.php

= Developer Features =

Show Login provides numerous hooks and filters for developers:

* Add two-factor authentication fields
* Customize all labels and text
* Modify the redirect behavior
* Add custom fields to the form
* Integrate with other authentication systems

See the [GitHub repository](https://github.com/cartpauj/show-login) for full developer documentation.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins > Add New
3. Search for "Show Login"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress dashboard
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"

= Usage =

1. After activation, the plugin works automatically
2. Add `?sl=true` to any URL on your site
3. Non-logged-in users will see the login popup
4. That's it! No configuration needed.

== Frequently Asked Questions ==

= How do I trigger the login popup? =

Add `?sl=true` to any URL on your site. For example:
* `https://yoursite.com/page?sl=true`
* `https://yoursite.com/shop?sl=true`

If the URL already has parameters, use `&sl=true`:
* `https://yoursite.com/page?id=123&sl=true`

= What happens after a user logs in? =

The page reloads with the user logged in. All URL parameters are preserved except the `sl=true` parameter is removed. The user stays on the same page they were viewing.

= Does this work with page caching? =

Yes! The plugin is fully compatible with all page caching solutions (WP Rocket, W3 Total Cache, Cloudflare, etc.). It checks login status via AJAX, which always executes fresh PHP with the user's cookies, bypassing the page cache completely. Security nonces are also generated fresh per user via AJAX and never cached, ensuring maximum security even with aggressive caching.

= How do I customize the popup text? =

You can use WordPress filters in your theme's functions.php:

`
add_filter('show_login_popup_title', function($title) {
    return 'Welcome Back!';
});
`

See the FAQ section for more examples or check the developer documentation.

= Can I add two-factor authentication? =

Yes! The plugin provides hooks that allow you to add 2FA fields and verification. See the developer documentation for examples.

= Does it limit login attempts? =

Not by itself. Use a security plugin such as Wordfence, Limit Login Attempts Reloaded, or Solid Security. The popup logs people in through WordPress's own login function, so those plugins protect it automatically, just like wp-login.php.

Older versions had a built-in rate limiter. It was removed because it trusted IP headers visitors can fake, so it could be bypassed and used to lock real people out. Its filters (`show_login_enable_rate_limiting`, `show_login_max_attempts`, `show_login_rate_limit_window`, `show_login_client_ip`) no longer do anything.

= Is the popup mobile-friendly? =

Yes! The popup is fully responsive and works on all devices.

= Can I close the popup without logging in? =

Yes! Users can close the popup by:
* Clicking the X button in the top-right corner
* Clicking outside the popup on the overlay
* Pressing the ESC key on their keyboard

= Does this work with other plugins? =

Yes! Show Login uses standard WordPress authentication and provides hooks for other plugins to integrate. It works with most membership, security, and authentication plugins.

= How do I translate the plugin? =

Install Loco Translate, go to Loco Translate > Plugins > Show Login, and add a language. Keep Loco's default save location (Custom) or use System. Don't save translations inside the plugin's own folder: plugin updates replace that folder and the translations would be lost.

= Where can I get support? =

* Check the [plugin documentation](https://github.com/cartpauj/show-login)
* Submit issues on [GitHub](https://github.com/cartpauj/show-login/issues)
* Visit the [support forum](https://wordpress.org/support/plugin/show-login/)

= Can I customize the popup design? =

Yes! For button colors, use the provided filters (see customization examples below). For other styles, you can override the plugin's CSS:

`
#show-login-popup {
    max-width: 500px !important;
}
`

Add custom CSS to your theme or use a custom CSS plugin.

== Screenshots ==

1. Login popup centered on the page with overlay
2. Clean, accessible form with username and password fields
3. Error messages display in the popup without page reload
4. Mobile-responsive design works on all devices

== Customization Examples ==

= Change the popup title =

`
add_filter('show_login_popup_title', function($title) {
    return 'Sign In to Continue';
});
`

= Change field labels =

`
add_filter('show_login_username_label', function($label) {
    return 'Email Address';
});

add_filter('show_login_submit_label', function($label) {
    return 'Sign In';
});
`

= Add a logo or branding =

`
add_action('show_login_after_title', function() {
    echo '<div style="text-align: center; margin: 15px 0;">';
    echo '<img src="' . esc_url(get_stylesheet_directory_uri() . '/images/logo.png') . '" alt="Logo" style="max-width: 200px;">';
    echo '</div>';
});
`

= Add a "Forgot Password" link =

`
add_action('show_login_form_end', function() {
    $url = wp_lostpassword_url();
    echo '<p style="text-align: center; margin-top: 15px;">';
    echo '<a href="' . esc_url($url) . '">Forgot your password?</a>';
    echo '</p>';
});
`

= Redirect to a specific page after login =

`
add_filter('show_login_redirect_url', function($redirect_url, $current_url) {
    return home_url('/dashboard');
}, 10, 2);
`

= Log successful logins =

`
add_action('show_login_success', function($user) {
    error_log("User {$user->user_login} logged in successfully");
});
`

= Change button colors =

`
add_filter('show_login_button_bg_color', function($color) {
    return '#e74c3c'; // Red background
});

add_filter('show_login_button_hover_bg_color', function($color) {
    return '#c0392b'; // Darker red on hover
});

add_filter('show_login_button_text_color', function($color) {
    return '#ffffff'; // White text
});
`

== Upgrade Notice ==

= 1.2.0 =
The built-in login attempt limit has been removed. Use a security plugin like Wordfence or Limit Login Attempts Reloaded to limit login attempts. It protects the popup automatically.

= 1.1.0 =
Adds social login buttons to the popup (MemberPress / WishList Member Social Login and Login with Google) and fixes several redirect and lockout bugs.

= 1.0.0 =
Initial release of Show Login. Enjoy seamless front-end login popups!

== Changelog ==

= 1.2.0 =
* Breaking: The built-in login attempt limit is gone. It could be tricked with fake IP addresses and used to lock real people out. Use a security plugin like Wordfence, Limit Login Attempts Reloaded, or Solid Security instead. It protects the popup automatically. The `show_login_enable_rate_limiting`, `show_login_max_attempts`, `show_login_rate_limit_window`, and `show_login_client_ip` filters no longer do anything.
* Fix: The `show_login_redirect_url` filter now works. Before, it never ran in the popup.
* Fix: Opening the popup no longer makes your server wait a second for every visitor. The spinner still shows for at least a second, but the browser handles it.
* Fix: If the server sends back a broken reply, the spinner closes instead of getting stuck.
* Security: After two-factor login, people can only be sent to pages on your site.
* Security: Error messages from other plugins are cleaned up before the popup shows them, so they can't run scripts.

= 1.1.1 =
* Fix: The popup's status and error messages ("Checking login status...", "You're not logged in", and others) were always in English. They can now be translated.
* New: Works with Loco Translate out of the box.

= 1.1.0 =
* New: Social login buttons in the popup for MemberPress and WishList Member Social Login, and Login with Google. After logging in, people return to the page they were on.
* New: `show_login_social_buttons` action for adding your own login buttons above the form.
* New: Install with Composer (`composer require cartpauj/show-login`) or download the zip from GitHub releases.
* Fix: Links with special characters (like `%20`) were changed when sending people back after login or two-factor.
* Fix: The lockout message said "try again in 0 minutes" on sites using Redis or Memcached.
* Fix: Cloudflare's Turnstile script no longer loads on sites that don't use Turnstile.
* Fix: Translations couldn't be found because the languages folder path was wrong.
* Fix: Plugin and readme links pointed at the wrong GitHub repository.

= 1.0.0 =
* Initial release
* Front-end login popup triggered by URL parameter
* Pure JavaScript implementation with no dependencies
* Full page caching compatibility (WP Rocket, W3 Total Cache, etc.)
* Instant loading spinner with smooth transitions
* Built-in rate limiting and security features
* Username enumeration prevention
* Full WordPress authentication integration
* Extensive hooks and filters for developers
* Complete translation support
* WCAG accessibility compliant
* Mobile responsive design

== Developer Documentation ==

Show Login provides extensive hooks for customization and integration.

= Action Hooks =

* `show_login_after_title` - Fires after the popup title (perfect for logos/branding)
* `show_login_form_start` - Fires at the beginning of the form
* `show_login_form_middle` - Fires before the submit button (perfect for 2FA fields)
* `show_login_form_end` - Fires at the end of the form
* `show_login_before_authenticate` - Fires before authentication attempt
* `show_login_after_authenticate` - Fires after authentication attempt
* `show_login_success` - Fires on successful login

= Filter Hooks =

* `show_login_popup_title` - Customize the popup title
* `show_login_username_label` - Customize username field label
* `show_login_password_label` - Customize password field label
* `show_login_remember_label` - Customize remember me label
* `show_login_submit_label` - Customize submit button text
* `show_login_button_bg_color` - Customize button background color
* `show_login_button_hover_bg_color` - Customize button hover background color
* `show_login_button_text_color` - Customize button text color
* `show_login_credentials` - Modify credentials before authentication
* `show_login_error_message` - Customize error messages
* `show_login_redirect_url` - Modify redirect destination
* `show_login_suppress_loading_state` - Suppress loading spinner and status messages

For complete developer documentation, examples, and code snippets, visit the [GitHub repository](https://github.com/cartpauj/show-login).

== Privacy Policy ==

Show Login does not collect, store, or transmit any personal data beyond what WordPress core collects during the login process. The plugin:

* Does not use cookies beyond WordPress's standard authentication cookies
* Does not track users
* Does not send data to external services
* Does not store any information in external databases
* Does not store IP addresses

== Credits ==

Show Login is developed and maintained by [cartpauj](https://github.com/cartpauj).
