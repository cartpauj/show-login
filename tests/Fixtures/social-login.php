<?php

/**
 * Minimal stand-ins for the Caseproof social login add-ons
 * (MemberPress / WishList Member Social Login).
 *
 * Each add-on exposes a function returning its handler, whose public
 * $oauthProviders holds provider objects. Tests set the handlers through
 * $GLOBALS['show_login_test_social']; null means "add-on not installed".
 *
 * @package ShowLogin\Tests
 */

// phpcs:disable

class Show_Login_Test_Social_Provider {
    public $id;
    public $isEnabled;
    public $isVerified;
    private $name;
    private $integration;

    public function __construct(string $id, string $name, bool $enabled = true, bool $verified = true, string $integration = 'memberpress') {
        $this->id          = $id;
        $this->name        = $name;
        $this->isEnabled   = $enabled;
        $this->isVerified  = $verified;
        $this->integration = $integration;
    }

    public function getName(): string {
        return $this->name;
    }

    // Like the real library during an ajax request: "redir" is empty and the URL is not escaped.
    public function getSocialLoginUrl(string $providerId, string $type): string {
        return add_query_arg(
            [
                $this->integration . '-social-login-request' => $providerId,
                'type'  => $type,
                'redir' => '',
            ],
            get_bloginfo('url')
        );
    }
}

class Show_Login_Test_Social_Handler {
    public $oauthProviders;

    public function __construct(array $providers = []) {
        $this->oauthProviders = $providers;
    }
}

function show_login_test_social_reset(): void {
    $GLOBALS['show_login_test_social'] = ['mpsl' => null, 'wlmsl' => null];
}
show_login_test_social_reset();

function mpslHandlerInstance() {
    return $GLOBALS['show_login_test_social']['mpsl'];
}

function wlmslHandlerInstance() {
    return $GLOBALS['show_login_test_social']['wlmsl'];
}
