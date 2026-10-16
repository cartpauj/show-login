<?php

/**
 * Minimal stand-ins for the Simple Cloudflare Turnstile plugin's functions.
 *
 * Show Login only treats Turnstile as active when these functions exist AND the
 * cfturnstile_key / cfturnstile_secret options are set, so defining them here
 * does not switch the integration on for unrelated tests. Tests drive the
 * behaviour through $GLOBALS['show_login_test_turnstile'].
 *
 * @package ShowLogin\Tests
 */

// phpcs:disable

function show_login_test_turnstile_reset(): void {
    $GLOBALS['show_login_test_turnstile'] = [
        'whitelisted'    => false,
        'check'          => ['success' => true],
        'failed_message' => 'Turnstile says no.',
        'field_calls'    => [],
        'check_calls'    => 0,
    ];
}
show_login_test_turnstile_reset();

function cfturnstile_field_show(...$args): void {
    $GLOBALS['show_login_test_turnstile']['field_calls'][] = $args;
    echo '<div class="cf-turnstile" id="cf-turnstile-show-login"></div>';
}

function cfturnstile_whitelisted(): bool {
    return $GLOBALS['show_login_test_turnstile']['whitelisted'];
}

function cfturnstile_check(): array {
    $GLOBALS['show_login_test_turnstile']['check_calls']++;
    return $GLOBALS['show_login_test_turnstile']['check'];
}

function cfturnstile_failed_message(): string {
    return $GLOBALS['show_login_test_turnstile']['failed_message'];
}
