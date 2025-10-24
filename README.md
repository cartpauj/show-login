# Show Login

A lightweight WordPress plugin that provides a front-end login popup triggered by URL parameter. Built with modern PHP 7.4+ standards, vanilla JavaScript, and clean architecture.

## Features

- **Lightweight** - Minimal footprint with clean, organized code structure
- **Pure JavaScript** - No jQuery or other libraries required
- **Cache Compatible** - Works perfectly with all page caching solutions (WP Rocket, LiteSpeed, etc.)
- **Turnstile Integration** - Built-in support for Cloudflare Turnstile CAPTCHA with dynamic script loading
- **Secure** - Rate limiting, nonce verification, CAPTCHA support, and proper sanitization
- **Fast UX** - Instant loading spinner with smooth transitions
- **Accessible** - WCAG compliant with proper ARIA attributes
- **Extensible** - Multiple hooks for two-factor authentication, CAPTCHA, and customization
- **Translation Ready** - Fully internationalized with i18n support
- **Well Architected** - Proper MVC separation with dedicated classes for each concern
- **Multiple URL Triggers** - Supports `?sl=true`, `?sl=1`, `?show_login=true`, and `?show_login=1`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Installation

1. Download the plugin files
2. Upload the `show-login` directory to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. The plugin works automatically when the URL parameter is present

## Usage

### Basic Usage

Add any of these URL parameters to trigger the login popup for non-logged-in users:

```
https://example.com/page?sl=true
https://example.com/page?sl=1
https://example.com/page?show_login=true
https://example.com/page?show_login=1
```

All four parameter variations work identically. Parameters can be combined with other URL parameters:

```
https://example.com/shop/product?id=123&sl=true
https://example.com/members/content?category=premium&show_login=1
```

When a user successfully logs in:
- The page reloads with them logged in
- All URL parameters are preserved
- Only the popup trigger parameter is removed (e.g., `sl` or `show_login`)

### User Experience

1. User visits a page with a popup trigger parameter (e.g., `?sl=true`)
2. Popup appears **instantly** with loading spinner ("Checking login status...")
3. Login status checked via AJAX (bypasses page cache)
4. **If logged out:** Shows "You're not logged in" (1 second) → Login form appears
5. **If logged in:** Shows "You're already logged in!" (1 second) → Popup closes
6. User can close popup via:
   - X button in top-right corner
   - Clicking outside popup on overlay
   - Pressing ESC key
7. User enters credentials and submits
8. AJAX authentication happens without page reload
9. Errors display in popup without disruption
10. On success, page reloads and user is logged in

## Security Features

### Rate Limiting

Built-in IP-based rate limiting prevents brute-force attacks:
- Default: 5 attempts per 1 minute
- Uses WordPress transients for storage
- Clears on successful login
- Supports proxy/CDN headers (Cloudflare, X-Forwarded-For, etc.)
- Shows dynamic countdown in error message

Customize via filters:

```php
// Change max attempts
add_filter('show_login_max_attempts', function($max) {
    return 10; // Allow 10 attempts
});

// Change time window (in seconds)
add_filter('show_login_rate_limit_window', function($window) {
    return 3600; // 1 hour window (default: 60 = 1 minute)
});
```

### Nonce Verification

All AJAX requests are protected with WordPress nonces using `check_ajax_referer()`.

### Input Sanitization

- Username: `sanitize_text_field()` + `wp_unslash()`
- Password: `wp_unslash()` only (no sanitization to preserve special characters)
- Remember: `sanitize_text_field()` + boolean check
- URLs: `esc_url_raw()` to preserve structure

### Username Enumeration Prevention

Error messages are sanitized to prevent username enumeration attacks:
- Invalid username/email and incorrect password both return: "Invalid username or password"
- Specific error codes (`invalid_username`, `invalid_email`, `incorrect_password`) are replaced with generic messages
- Prevents attackers from determining which usernames exist on the site

### Page Caching Compatibility

The plugin is fully compatible with all page caching solutions:
- JavaScript checks `?sl=true` parameter client-side before any AJAX calls
- Login status checked via AJAX (always executes fresh PHP with user cookies)
- **Nonces generated fresh per user** - Created dynamically via AJAX, never cached
- **Redirect URLs computed per request** - Always accurate, never stale
- Works with WP Rocket, W3 Total Cache, Cloudflare, and all other caching plugins
- Instant loading spinner with status messages provides clear feedback:
  - "Checking login status..." (during AJAX)
  - "You're not logged in" (if logged out, shows 1 second before form)
  - "You're already logged in!" (if logged in, shows 1 second before closing)

## Cloudflare Turnstile Integration

Show Login includes built-in integration with the [Simple Cloudflare Turnstile](https://wordpress.org/plugins/simple-cloudflare-turnstile/) plugin for CAPTCHA protection.

### Features

- ✅ **Automatic Detection** - Activates only when Turnstile plugin is installed and configured
- ✅ **Cache Compatible** - Turnstile scripts load dynamically via JavaScript
- ✅ **All Settings Supported** - Works with all Turnstile themes, sizes, and appearance modes
- ✅ **Whitelist Support** - Respects Turnstile's IP/user whitelist settings
- ✅ **Button Disable Support** - Compatible with "disable submit button" option
- ✅ **Zero Configuration** - No setup needed beyond installing both plugins

### Setup

1. Install and activate [Simple Cloudflare Turnstile](https://wordpress.org/plugins/simple-cloudflare-turnstile/)
2. Configure your Cloudflare Turnstile **Site Key** and **Secret Key** in **Settings > Turnstile**
3. **That's it!** The integration works automatically

**Note:** You do NOT need to enable the "Login" checkbox in Turnstile settings - Show Login popup works independently with its own integration.

### Supported Settings

The integration works with **ALL** Turnstile settings:

- ✅ **Appearance Modes**: Always visible, Execute (invisible), or Interaction-only
- ✅ **Themes**: Light, Dark, or Auto
- ✅ **Sizes**: Normal, Compact, or Flexible
- ✅ **Disable Submit Button**: Supported in both visible and invisible modes
- ✅ **Languages**: All Cloudflare Turnstile languages
- ✅ **Whitelist**: IP and user whitelisting honored
- ✅ **Custom Error Messages**: Displayed in popup on validation failure

### How It Works

When both plugins are active and Turnstile is configured:

1. User visits page with popup trigger parameter
2. Turnstile API loads dynamically (cache-compatible)
3. Login popup appears with Turnstile widget between "Remember Me" and submit button
4. User interaction:
   - **Visible mode**: User completes the visible challenge, then submits
   - **Invisible mode**: User fills form and clicks submit, verification happens automatically
5. Turnstile token (`cf-turnstile-response`) sent with login request
6. Server validates token before authentication via `cfturnstile_check()`
7. If validation fails, user sees error message (customizable in Turnstile settings)
8. If validation succeeds, normal authentication proceeds

**Invisible Mode Behavior:**
- No visible widget shown to user
- Verification runs automatically when submit button is clicked
- Seamless UX - users don't see CAPTCHA unless flagged as suspicious
- All validation happens server-side - no difference in backend flow

### Filters

```php
// Skip Turnstile validation for specific users/conditions
add_filter('show_login_skip_turnstile', function($skip) {
    // Example: Skip for admin users testing
    if (current_user_can('manage_options')) {
        return true;
    }
    return $skip;
});
```

### Action Hooks

```php
// Track Turnstile failures
add_action('cfturnstile_show_login_failed', function($username) {
    error_log("Turnstile validation failed for user: {$username}");
});

// Track Turnstile successes
add_action('cfturnstile_show_login_success', function($username) {
    error_log("Turnstile validation passed for user: {$username}");
});
```

### Technical Details

**Cache Compatibility:**
- Turnstile scripts load dynamically via JavaScript when popup trigger parameter is detected
- Works with all caching plugins (WP Rocket, LiteSpeed Cache, W3 Total Cache, etc.)
- No cache variations needed for URL parameters

**Validation:**
- Server-side validation occurs before `wp_signon()` authentication
- Uses Turnstile's `cfturnstile_check()` function for validation
- Validates the `cf-turnstile-response` POST parameter
- Returns appropriate error messages on failure

**Styling:**
- Turnstile widget centered in popup
- Custom CSS classes: `.show-login-turnstile`, `.show-login-cf-turnstile`
- Respects Turnstile theme settings (light, dark, auto)
- Proper spacing and layout integration

## Developer Documentation

### Hooks & Filters

#### Action Hooks

**Form Hooks:**
```php
// Add logo/branding after the title
add_action('show_login_after_title', function() {
    echo '<div style="text-align: center; margin: 15px 0;">';
    echo '<img src="' . esc_url(get_stylesheet_directory_uri() . '/images/logo.png') . '" alt="Logo" style="max-width: 200px;">';
    echo '</div>';
});

// Add custom fields at the start of the form
add_action('show_login_form_start', function() {
    echo '<input type="hidden" name="custom_field" value="data">';
});

// Add 2FA fields before the submit button
add_action('show_login_form_middle', function() {
    echo '<div class="show-login-field">';
    echo '<label for="two-factor-code">2FA Code</label>';
    echo '<input type="text" id="two-factor-code" name="two_factor_code">';
    echo '</div>';
});

// Add links at the end of the form
add_action('show_login_form_end', function() {
    echo '<p><a href="/forgot-password">Forgot Password?</a></p>';
});
```

**Authentication Hooks:**
```php
// Before authentication attempt
add_action('show_login_before_authenticate', function($username) {
    error_log("Login attempt for user: {$username}");
});

// After authentication attempt
add_action('show_login_after_authenticate', function($user, $credentials) {
    if (is_wp_error($user)) {
        error_log("Failed login: " . $user->get_error_message());
    }
}, 10, 2);

// On successful login
add_action('show_login_success', function($user) {
    error_log("Successful login for user ID: {$user->ID}");
    // Send notification, log analytics, etc.
});
```

#### Filter Hooks

**Customize Labels:**
```php
add_filter('show_login_popup_title', function($title) {
    return 'Welcome Back!';
});

add_filter('show_login_username_label', function($label) {
    return 'Email Address';
});

add_filter('show_login_password_label', function($label) {
    return 'Your Password';
});

add_filter('show_login_remember_label', function($label) {
    return 'Keep me logged in';
});

add_filter('show_login_submit_label', function($label) {
    return 'Sign In';
});
```

**Modify Authentication:**
```php
// Modify credentials before authentication
add_filter('show_login_credentials', function($credentials) {
    // Add custom data
    $credentials['custom_data'] = 'value';
    return $credentials;
});

// Customize error messages
add_filter('show_login_error_message', function($message, $error) {
    if ($error->get_error_code() === 'invalid_username') {
        return 'The email address you entered is not registered.';
    }
    return $message;
}, 10, 2);
```

**Redirect Customization:**
```php
add_filter('show_login_redirect_url', function($redirect_url, $current_url) {
    // Always redirect to a specific page after login
    return home_url('/dashboard');
}, 10, 2);
```

**Button Styling:**
```php
// Change button background color
add_filter('show_login_button_bg_color', function($color) {
    return '#e74c3c'; // Default: #0073aa
});

// Change button hover background color
add_filter('show_login_button_hover_bg_color', function($color) {
    return '#c0392b'; // Default: #005a87
});

// Change button text color
add_filter('show_login_button_text_color', function($color) {
    return '#ffffff'; // Default: #fff
});
```

**Loading State:**
```php
// Suppress loading spinner and status messages
// Popup stays hidden until AJAX confirms user is logged out
add_filter('show_login_suppress_loading_state', '__return_true');
```

**Rate Limiting:**
```php
// Disable rate limiting completely
add_filter('show_login_enable_rate_limiting', '__return_false');

// Change max attempts
add_filter('show_login_max_attempts', function($max) {
    return 10; // Default: 5
});

// Change time window (in seconds)
add_filter('show_login_rate_limit_window', function($window) {
    return 1800; // 30 minutes (default: 60 = 1 minute)
});

// Override IP detection
add_filter('show_login_client_ip', function($ip) {
    // Use custom IP detection
    return $_SERVER['CUSTOM_IP_HEADER'] ?? $ip;
});
```

### Two-Factor Authentication Integration

Example integration with a 2FA plugin:

```php
// Add 2FA field to form
add_action('show_login_form_middle', function() {
    ?>
    <div class="show-login-field">
        <label for="show-login-2fa">Two-Factor Code</label>
        <input type="text" id="show-login-2fa" name="two_factor_code" autocomplete="one-time-code">
    </div>
    <?php
});

// Verify 2FA code after password authentication
add_action('show_login_after_authenticate', function($user, $credentials) {
    if (is_wp_error($user)) {
        return; // Password failed, don't check 2FA
    }

    // Get 2FA code from POST
    $two_factor_code = isset($_POST['two_factor_code']) ?
        sanitize_text_field(wp_unslash($_POST['two_factor_code'])) : '';

    // Verify 2FA code
    if (!verify_2fa_code($user->ID, $two_factor_code)) {
        wp_logout(); // Log them back out
        wp_send_json_error([
            'message' => 'Invalid two-factor authentication code.'
        ]);
    }
}, 10, 2);
```

### Customizing Styles

The plugin provides filters for button colors (see **Button Styling** section above). For other style customizations, use CSS specificity:

```css
/* Change popup width */
#show-login-popup {
    max-width: 500px !important;
}

/* Change overlay opacity */
#show-login-overlay {
    background-color: rgba(0, 0, 0, 0.9) !important;
}

/* Change input border color on focus */
.show-login-field input:focus {
    border-color: #e74c3c !important;
    box-shadow: 0 0 0 1px #e74c3c !important;
}
```

### JavaScript API

The plugin exposes the `showLoginData` object to JavaScript:

```javascript
// Available properties:
showLoginData.ajaxUrl      // WordPress AJAX URL
showLoginData.nonce        // Security nonce
showLoginData.redirectUrl  // URL to redirect after login
```

## Architecture

### File Structure

```
show-login/
├── show-login.php                          # Main plugin bootstrap
├── includes/
│   ├── class-show-login.php                # Main controller
│   ├── class-show-login-assets.php         # Asset management
│   ├── class-show-login-authenticator.php  # Authentication handler
│   ├── class-show-login-popup.php          # Popup rendering & AJAX check
│   ├── class-show-login-rate-limiter.php   # Rate limiting logic
│   └── class-show-login-turnstile.php      # Cloudflare Turnstile integration
├── assets/
│   ├── css/
│   │   └── show-login.css                  # Popup styles + Turnstile styles
│   └── js/
│       └── show-login.js                   # Front-end behavior + Turnstile loader
├── templates/
│   └── popup.php                           # Login popup HTML
├── README.md                               # Technical documentation
└── readme.txt                              # WordPress.org readme
```

### Architecture

The plugin follows a clean architecture pattern with separation of concerns:

**Main Controller (`Show_Login`)**
- Coordinates all plugin components
- Initializes dependencies
- Manages WordPress hooks
- Singleton pattern for single instance

**Assets Handler (`Show_Login_Assets`)**
- Enqueues CSS and JavaScript files
- Generates dynamic button color styles
- Manages script localization
- Handles redirect URL generation

**Authenticator (`Show_Login_Authenticator`)**
- Processes AJAX login requests
- Validates credentials
- Handles authentication success/failure
- Sanitizes error messages to prevent username enumeration

**Rate Limiter (`Show_Login_Rate_Limiter`)**
- IP-based rate limiting
- Transient storage for attempt tracking
- Configurable thresholds and windows
- CDN/proxy header support

**Popup Handler (`Show_Login_Popup`)**
- AJAX endpoint for login status check (cache-compatible)
- HTML rendering from template
- Returns popup data: HTML, nonce, redirect URL
- Instant feedback for logged-in users

**Template (`templates/popup.php`)**
- Semantic HTML markup
- WCAG accessibility compliant
- Action hooks for extensibility
- Filter hooks for customization

**Turnstile Integration (`Show_Login_Turnstile`)**
- Automatic detection of Turnstile plugin
- Dynamic script loading for cache compatibility
- Server-side validation before authentication
- Respects whitelist and all Turnstile settings

### Authentication Flow

```
1. User visits page with ?sl=true (or ?sl=1, ?show_login=true, ?show_login=1)
   ↓
2. Page loads (may be cached HTML)
   ↓
3. Assets enqueued (CSS, JS on all frontend pages)
   ↓
4. JavaScript: Check if popup trigger parameter in URL
   ↓
5. If YES: Load Turnstile API dynamically (if Turnstile plugin active)
   ↓
6. Show loading spinner immediately
   ↓
7. AJAX: Check login status (show_login_check_popup)
   ↓
8. PHP: is_user_logged_in() → Return show=true/false + HTML/nonce/redirectUrl
   ↓
9. JavaScript: If logged in → Show "You're already logged in!" (1s) → Close
             If logged out → Show "You're not logged in" (1s) → Show form
   ↓
10. Turnstile widget renders (if enabled) → User completes verification
   ↓
11. User submits form → AJAX request (show_login_authenticate)
   ↓
12. PHP: Rate limit check → Nonce verification → Turnstile validation → Sanitization
   ↓
13. Before hooks fire → wp_signon() → After hooks fire
   ↓
14. Success: Clear rate limit → Return success JSON
    Failure: Log attempt → Return error JSON
   ↓
15. JavaScript: Success → window.location.href = redirectUrl
               Failure → Show error in popup
```

## Best Practices

### When to Use This Plugin

✅ **Good use cases:**
- Members-only content requiring login
- Seamless login experience without leaving the page
- Custom landing pages with login CTAs
- Marketing campaigns with direct login links

❌ **Not recommended for:**
- Primary site-wide login (use standard WordPress login)
- High-security admin access
- As a complete replacement for wp-login.php

### Performance Considerations

- Assets only load when needed (logged-out users with `?sl=true`)
- Inline CSS/JS eliminates HTTP requests
- Rate limiting uses efficient transients
- No database queries on every page load

### Security Recommendations

1. Always use HTTPS in production
2. Enable Cloudflare Turnstile for CAPTCHA protection (see Turnstile Integration section)
3. Enable two-factor authentication via hooks (see Two-Factor Authentication Integration section)
4. Monitor failed login attempts using action hooks
5. Keep WordPress and plugins updated
6. Consider lowering rate limit thresholds for high-security sites

## Troubleshooting

### Popup doesn't appear

- Verify user is logged out
- Check URL has one of: `?sl=true`, `?sl=1`, `?show_login=true`, or `?show_login=1`
- Check JavaScript console for errors
- Verify plugin is activated

### Turnstile widget not showing

- Verify Simple Cloudflare Turnstile plugin is installed and activated
- Check that Turnstile keys are configured in **Settings > Turnstile**
- Check JavaScript console for Turnstile API loading errors
- Verify user is not whitelisted in Turnstile settings
- **Note:** If using invisible mode (`appearance: execute`), the widget is intentionally hidden - verification happens automatically on submit

### Turnstile validation always failing

- Verify both Site Key and Secret Key are correct in Turnstile settings
- Check that keys match the domain (Turnstile keys are domain-specific)
- Ensure server can make outbound HTTPS requests to `challenges.cloudflare.com`
- Check for JavaScript errors preventing Turnstile from loading
- Test with a different browser or incognito mode

### Redirect not working after login

- Check for JavaScript errors in console
- Verify `showLoginData.redirectUrl` is correct
- Check for conflicting plugins that modify redirects

### Rate limiting issues

- Clear transients: `delete_transient('show_login_attempts_*')`
- Verify IP detection is working correctly
- Check proxy/CDN configuration

## Contributing

This plugin is maintained by [Caseproof](https://caseproof.com).

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Changelog

### 1.0.0
- Initial release
- Front-end login popup with URL parameter triggers (`?sl=true`, `?sl=1`, `?show_login=true`, `?show_login=1`)
- Built-in Cloudflare Turnstile integration with dynamic script loading
- Cache-compatible architecture (works with all caching plugins)
- Rate limiting and security features
- Extensive hooks for extensibility (2FA, CAPTCHA, custom fields)
- Full translation support
- WCAG accessibility compliant
