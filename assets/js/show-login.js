/**
 * Show Login - Front-end JavaScript
 *
 * Handles popup display, form submission, and AJAX authentication.
 * Cache-compatible: checks URL parameter and user login status before showing popup.
 */
(function() {
    'use strict';

    /**
     * Check if popup trigger parameter is in the URL
     * Supports: ?sl=true, ?sl=1, ?show_login=true, ?show_login=1
     */
    function shouldCheckPopup() {
        const urlParams = new URLSearchParams(window.location.search);

        // Check 'sl' parameter
        const sl = urlParams.get('sl');
        if (sl === 'true' || sl === '1') {
            return true;
        }

        // Check 'show_login' parameter
        const showLogin = urlParams.get('show_login');
        if (showLogin === 'true' || showLogin === '1') {
            return true;
        }

        return false;
    }

    /**
     * Initialize popup on page load
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Exit early if trigger parameter is not in URL (most efficient check)
        if (!shouldCheckPopup()) {
            return;
        }

        // Check if we should show popup (handles caching + login status)
        // Note: Loading spinner may or may not show based on server-side filter
        checkAndShowPopup();
    });

    /**
     * Show popup with loading spinner immediately
     */
    function showLoadingPopup() {
        const loadingHTML = `
            <div id="show-login-overlay" class="show-login-active">
                <div id="show-login-popup">
                    <div class="show-login-loading-state">
                        <div class="show-login-spinner"></div>
                        <p>Checking login status...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', loadingHTML);
    }

    /**
     * Load Turnstile API script dynamically
     */
    function loadTurnstileScript(callback) {
        // Check if Turnstile is already loaded
        if (typeof window.turnstile !== 'undefined') {
            callback();
            return;
        }

        // Check if script is already being loaded
        if (document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]')) {
            // Wait for it to load
            const checkInterval = setInterval(function() {
                if (typeof window.turnstile !== 'undefined') {
                    clearInterval(checkInterval);
                    callback();
                }
            }, 100);
            return;
        }

        // Load Turnstile API script
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.setAttribute('data-cfasync', 'false');
        script.onload = callback;
        script.onerror = function() {
            console.error('Show Login: Failed to load Turnstile script');
            callback(); // Continue anyway
        };
        document.head.appendChild(script);
    }

    /**
     * Check via AJAX if popup should be shown and display it
     */
    function checkAndShowPopup() {
        // Check if loading should be suppressed (from localized data)
        const shouldSuppressLoading = showLoginData.suppressLoading || false;

        // Show loading popup immediately if not suppressed
        if (!shouldSuppressLoading) {
            showLoadingPopup();
        }

        // Load Turnstile script first (if needed), then check popup
        loadTurnstileScript(function() {
            // Make AJAX request to check login status
            fetch(showLoginData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=show_login_check_popup'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.show) {
                    // User is logged out - show form (with or without loading messages)
                    if (shouldSuppressLoading) {
                        // Skip loading messages, show form immediately
                        showFormImmediately(data.data.html, data.data.nonce, data.data.redirectUrl);
                    } else {
                        // Show loading messages before form
                        replaceLoadingWithForm(data.data.html, data.data.nonce, data.data.redirectUrl);
                    }
                } else {
                    // User is already logged in
                    if (!shouldSuppressLoading) {
                        showLoggedInMessage();
                    }
                    // Otherwise suppress is enabled, nothing to show
                }
            })
            .catch(error => {
                console.error('Show Login: Failed to check popup status', error);
                // On error, close the loading popup if it exists
                closePopup();
            });
        });
    }

    /**
     * Show "You're already logged in!" message before closing
     */
    function showLoggedInMessage() {
        // Update loading state to success message
        const loadingState = document.querySelector('.show-login-loading-state');
        if (loadingState) {
            loadingState.innerHTML = `
                <div class="show-login-spinner show-login-success"></div>
                <p>You're already logged in!</p>
            `;
        }

        // Close popup after 1 second
        setTimeout(function() {
            closePopup();
        }, 1000);
    }

    /**
     * Show form immediately without loading messages
     */
    function showFormImmediately(html, nonce, redirectUrl) {
        // Remove loading popup if it exists
        const overlay = document.getElementById('show-login-overlay');
        if (overlay) {
            overlay.remove();
        }

        // Inject actual popup HTML
        document.body.insertAdjacentHTML('beforeend', html);

        // Update nonce and redirect URL
        showLoginData.nonce = nonce;
        showLoginData.redirectUrl = redirectUrl;

        // Initialize popup behavior
        initializePopup();

        // Show the popup
        const newOverlay = document.getElementById('show-login-overlay');
        if (newOverlay) {
            newOverlay.classList.add('show-login-active');
        }
    }

    /**
     * Replace loading state with actual login form
     */
    function replaceLoadingWithForm(html, nonce, redirectUrl) {
        // Update to "You're not logged in" message
        const loadingState = document.querySelector('.show-login-loading-state');
        if (loadingState) {
            loadingState.innerHTML = `
                <div class="show-login-spinner show-login-info"></div>
                <p>You're not logged in</p>
            `;
        }

        // After 1 second, show the login form
        setTimeout(function() {
            // Remove loading popup
            const overlay = document.getElementById('show-login-overlay');
            if (overlay) {
                overlay.remove();
            }

            // Inject actual popup HTML
            document.body.insertAdjacentHTML('beforeend', html);

            // Update nonce and redirect URL
            showLoginData.nonce = nonce;
            showLoginData.redirectUrl = redirectUrl;

            // Initialize popup behavior
            initializePopup();

            // Show the popup
            const newOverlay = document.getElementById('show-login-overlay');
            if (newOverlay) {
                newOverlay.classList.add('show-login-active');
            }
        }, 1000);
    }

    /**
     * Initialize popup event handlers
     */
    function initializePopup() {
        const closeBtn = document.getElementById('show-login-close');
        const overlay = document.getElementById('show-login-overlay');
        const form = document.getElementById('show-login-form');

        // Close button
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closePopup();
            });
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePopup();
            }
        });

        // Form submission
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleLogin();
            });
        }
    }

    /**
     * Close popup
     */
    function closePopup() {
        const overlay = document.getElementById('show-login-overlay');
        if (overlay) {
            overlay.classList.remove('show-login-active');
            // Remove after animation completes
            setTimeout(function() {
                overlay.remove();
            }, 300);
        }
    }

    /**
     * Handle login form submission
     */
    function handleLogin() {
        const username = document.getElementById('show-login-username').value;
        const password = document.getElementById('show-login-password').value;
        const remember = document.getElementById('show-login-remember').checked;
        const submitBtn = document.getElementById('show-login-submit');
        const errorDiv = document.getElementById('show-login-error');

        // Clear previous errors
        errorDiv.classList.remove('show-login-visible');
        errorDiv.innerHTML = '';

        // Validation
        if (!username || !password) {
            showError('Please enter both username and password.');
            return;
        }

        // Disable submit button and show loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('show-login-loading');

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'show_login_authenticate');
        formData.append('nonce', showLoginData.nonce);
        formData.append('username', username);
        formData.append('password', password);
        formData.append('remember', remember ? '1' : '0');

        // Send AJAX request
        fetch(showLoginData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Successful login - reload page
                window.location.href = showLoginData.redirectUrl;
            } else {
                // Show error message
                showError(data.data.message || 'Login failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.classList.remove('show-login-loading');
            }
        })
        .catch(error => {
            showError('An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.classList.remove('show-login-loading');
        });
    }

    /**
     * Display error message
     */
    function showError(message) {
        const errorDiv = document.getElementById('show-login-error');
        errorDiv.innerHTML = message;
        errorDiv.classList.add('show-login-visible');
    }

    /**
     * Cloudflare Turnstile callback
     * Called when Turnstile verification completes (if disable button option is enabled)
     */
    window.showLoginTurnstileCallback = function() {
        const submitBtn = document.getElementById('show-login-submit');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.style.pointerEvents = '';
            submitBtn.style.opacity = '';
        }
    };
})();
