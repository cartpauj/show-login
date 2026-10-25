<?php

/**
 * Minimal stand-in for Wordfence Login Security's 2FA check.
 *
 * Show Login only needs the WordfenceLS controller class to exist, plus
 * Wordfence's `authenticate` behavior: a user with 2FA gets
 * `wfls_twofactor_required` until the code arrives in the `wfls-token` POST
 * field. Tests drive it through $GLOBALS['show_login_test_wordfence'].
 *
 * @package ShowLogin\Tests
 */

// phpcs:disable

namespace WordfenceLS {
    class Controller_WordfenceLS {}
}

namespace {
    function show_login_test_wordfence_reset(): void {
        $GLOBALS['show_login_test_wordfence'] = [
            'users_with_2fa' => [],
            'valid_code'     => '123456',
        ];
    }
    show_login_test_wordfence_reset();

    // Same order as Wordfence: runs after the password check, so $user is a WP_User only if it was right.
    add_filter('authenticate', static function ($user) {
        $state = $GLOBALS['show_login_test_wordfence'];

        if (!($user instanceof WP_User) || !in_array($user->ID, $state['users_with_2fa'], true)) {
            return $user;
        }

        if (!array_key_exists('wfls-token', $_POST)) {
            return new WP_Error('wfls_twofactor_required', '<strong>CODE REQUIRED</strong>: Please provide your 2FA code when prompted.');
        }

        if ($_POST['wfls-token'] === $state['valid_code']) {
            return $user;
        }

        return new WP_Error('wfls_twofactor_failed', '<strong>CODE INVALID</strong>: The 2FA code provided is either expired or invalid. Please try again.');
    }, 100);
}
