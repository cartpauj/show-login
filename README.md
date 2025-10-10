# Show Login

A lightweight, single-file WordPress plugin that provides a front-end login popup triggered by URL parameter. Built with modern PHP 7.4+ standards and vanilla JavaScript.

## Features

- **Lightweight** - Single file plugin with no external dependencies
- **Pure JavaScript** - No jQuery or other libraries required
- **Secure** - Rate limiting, nonce verification, and proper sanitization
- **Accessible** - WCAG compliant with proper ARIA attributes
- **Extensible** - Multiple hooks for two-factor authentication and customization
- **Translation Ready** - Fully internationalized with i18n support

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Installation

1. Download `show-login.php`
2. Upload to `/wp-content/plugins/show-login/`
3. Activate the plugin through the WordPress admin
4. The plugin works automatically when the URL parameter is present

## Usage

### Basic Usage

Add `?sl=true` to any URL on your site to trigger the login popup for non-logged-in users:

```
https://example.com/page?sl=true
https://example.com/shop/product?id=123&sl=true
```

When a user successfully logs in:
- The page reloads with them logged in
- All URL parameters are preserved
- Only the `sl=true` parameter is removed

### User Experience

1. User visits a page with `?sl=true` parameter
2. Popup appears centered on screen with overlay
3. User can close popup via:
   - X button in top-right corner
   - Clicking outside popup on overlay
   - Pressing ESC key
4. User enters credentials and submits
5. AJAX authentication happens without page reload
6. Errors display in popup without disruption
7. On success, page reloads and user is logged in

## Security Features

### Rate Limiting

Built-in IP-based rate limiting prevents brute-force attacks:
- Default: 5 attempts per 15 minutes
- Uses WordPress transients for storage
- Clears on successful login
- Supports proxy/CDN headers (Cloudflare, X-Forwarded-For, etc.)

Customize via filters:

```php
// Change max attempts
add_filter('show_login_max_attempts', function($max) {
    return 10; // Allow 10 attempts
});

// Change time window (in seconds)
add_filter('show_login_rate_limit_window', function($window) {
    return 3600; // 1 hour window
});
```

### Nonce Verification

All AJAX requests are protected with WordPress nonces using `check_ajax_referer()`.

### Input Sanitization

- Username: `sanitize_text_field()` + `wp_unslash()`
- Password: `wp_unslash()` only (no sanitization to preserve special characters)
- Remember: `sanitize_text_field()` + boolean check
- URLs: `esc_url_raw()` to preserve structure

## Developer Documentation

### Hooks & Filters

#### Action Hooks

**Form Hooks:**
```php
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

**Rate Limiting:**
```php
add_filter('show_login_max_attempts', function($max) {
    return 10; // Default: 5
});

add_filter('show_login_rate_limit_window', function($window) {
    return 1800; // 30 minutes (default: 900 = 15 minutes)
});

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

The plugin uses inline CSS for lightweight delivery. To customize styles, use CSS specificity:

```css
/* Change popup width */
#show-login-popup {
    max-width: 500px !important;
}

/* Change button color */
#show-login-submit {
    background: #e74c3c !important;
}

#show-login-submit:hover {
    background: #c0392b !important;
}

/* Change overlay opacity */
#show-login-overlay {
    background-color: rgba(0, 0, 0, 0.9) !important;
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
├── show-login.php    # Main plugin file (single file)
├── README.md         # Technical documentation
└── readme.txt        # WordPress.org readme
```

### Class Structure

```
Show_Login (Singleton)
├── get_instance()          # Get singleton instance
├── load_textdomain()       # Load translations
├── should_show_popup()     # Check if popup should display
├── enqueue_assets()        # Enqueue CSS/JS
├── get_redirect_url()      # Build redirect URL
├── get_inline_css()        # Generate CSS
├── get_inline_js()         # Generate JavaScript
├── render_popup_html()     # Output HTML
├── handle_login_ajax()     # Process login via AJAX
├── is_rate_limited()       # Check rate limit
├── log_failed_attempt()    # Log failed login
├── clear_failed_attempts() # Clear attempts on success
└── get_client_ip()         # Detect client IP
```

### Authentication Flow

```
1. User visits page with ?sl=true
   ↓
2. Plugin checks: !is_admin() && !is_user_logged_in() && should_show_popup()
   ↓
3. Assets enqueued (CSS, JS, localized data)
   ↓
4. HTML rendered in footer
   ↓
5. JavaScript shows popup on DOMContentLoaded
   ↓
6. User submits form → AJAX request
   ↓
7. PHP: Rate limit check → Nonce verification → Sanitization
   ↓
8. Before hooks fire → wp_signon() → After hooks fire
   ↓
9. Success: Clear rate limit → Return success JSON
   Failure: Log attempt → Return error JSON
   ↓
10. JavaScript: Success → window.location.href = redirectUrl
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
2. Consider adding CAPTCHA for additional security
3. Enable two-factor authentication
4. Monitor failed login attempts
5. Keep WordPress and plugins updated

## Troubleshooting

### Popup doesn't appear

- Verify user is logged out
- Check URL has `?sl=true` or `&sl=true`
- Check JavaScript console for errors
- Verify plugin is activated

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
- Front-end login popup with URL parameter trigger
- Rate limiting and security features
- Extensive hooks for extensibility
- Full translation support
