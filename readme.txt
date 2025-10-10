=== Show Login ===
Contributors: caseproof
Tags: login, popup, ajax, frontend, modal
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight front-end login popup triggered by a URL parameter. Perfect for seamless login experiences without leaving the page.

== Description ==

Show Login adds a beautiful, accessible login popup to your WordPress site that appears when you add a special parameter to any URL. Perfect for membership sites, marketing campaigns, or anywhere you want users to log in without leaving their current page.

= Key Features =

* **Lightweight & Fast** - Single file plugin with no external dependencies
* **Pure JavaScript** - No jQuery or other libraries needed
* **Seamless Experience** - Users log in without leaving the page
* **Secure by Default** - Built-in rate limiting and security features
* **Fully Accessible** - WCAG compliant with proper ARIA attributes
* **Translation Ready** - Supports internationalization
* **Developer Friendly** - Extensive hooks for customization

= How It Works =

Simply add `?sl=true` to any URL on your site:

* `https://yoursite.com/page?sl=true`
* `https://yoursite.com/shop?sl=true`
* `https://yoursite.com/post?id=123&sl=true`

When a logged-out user visits the URL, they'll see a clean login popup. After logging in, they stay on the same page - no redirects to wp-login.php needed!

= Perfect For =

* Membership sites requiring login
* Protected content pages
* Marketing campaigns with login links
* Email newsletters with direct login access
* Social media links requiring authentication
* Seamless user experiences

= Security Features =

* **Rate Limiting** - Prevents brute-force attacks (5 attempts per 15 minutes by default)
* **Nonce Verification** - All requests are validated
* **Proper Sanitization** - All inputs are cleaned and validated
* **WordPress Authentication** - Uses core WordPress login functions

= Developer Features =

Show Login provides numerous hooks and filters for developers:

* Add two-factor authentication fields
* Customize all labels and text
* Modify the redirect behavior
* Add custom fields to the form
* Integrate with other authentication systems
* Customize rate limiting settings

See the [GitHub repository](https://github.com/caseproof/show-login) for full developer documentation.

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

= How does rate limiting work? =

The plugin limits login attempts to 5 per 15 minutes per IP address by default. This prevents brute-force attacks. Failed attempts are tracked and cleared upon successful login.

= Can I change the rate limit settings? =

Yes, developers can use filters to customize rate limiting:

`
// Allow 10 attempts
add_filter('show_login_max_attempts', function($max) {
    return 10;
});

// Change window to 30 minutes
add_filter('show_login_rate_limit_window', function($window) {
    return 1800; // seconds
});
`

= Is the popup mobile-friendly? =

Yes! The popup is fully responsive and works on all devices.

= Can I close the popup without logging in? =

Yes! Users can close the popup by:
* Clicking the X button in the top-right corner
* Clicking outside the popup on the overlay
* Pressing the ESC key on their keyboard

= Does this work with other plugins? =

Yes! Show Login uses standard WordPress authentication and provides hooks for other plugins to integrate. It works with most membership, security, and authentication plugins.

= Where can I get support? =

* Check the [plugin documentation](https://github.com/caseproof/show-login)
* Submit issues on [GitHub](https://github.com/caseproof/show-login/issues)
* Visit the [support forum](https://wordpress.org/support/plugin/show-login/)

= Can I customize the popup design? =

Yes! You can override the plugin's CSS with your own styles:

`
#show-login-popup {
    max-width: 500px !important;
}

#show-login-submit {
    background: #your-color !important;
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

== Upgrade Notice ==

= 1.0.0 =
Initial release of Show Login. Enjoy seamless front-end login popups!

== Changelog ==

= 1.0.0 =
* Initial release
* Front-end login popup triggered by URL parameter
* Pure JavaScript implementation with no dependencies
* Built-in rate limiting and security features
* Full WordPress authentication integration
* Extensive hooks and filters for developers
* Complete translation support
* WCAG accessibility compliant
* Mobile responsive design

== Developer Documentation ==

Show Login provides extensive hooks for customization and integration.

= Action Hooks =

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
* `show_login_credentials` - Modify credentials before authentication
* `show_login_error_message` - Customize error messages
* `show_login_redirect_url` - Modify redirect destination
* `show_login_max_attempts` - Adjust rate limit threshold
* `show_login_rate_limit_window` - Adjust rate limit window
* `show_login_client_ip` - Override IP detection

For complete developer documentation, examples, and code snippets, visit the [GitHub repository](https://github.com/caseproof/show-login).

== Privacy Policy ==

Show Login does not collect, store, or transmit any personal data beyond what WordPress core collects during the login process. The plugin:

* Does not use cookies beyond WordPress's standard authentication cookies
* Does not track users
* Does not send data to external services
* Does not store any information in external databases
* Uses WordPress transients to temporarily store rate limiting data (IP addresses are hashed)

== Credits ==

Show Login is developed and maintained by [Caseproof](https://caseproof.com).
