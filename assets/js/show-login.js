/**
 * Show Login - Front-end JavaScript
 *
 * Handles popup display, form submission, and AJAX authentication.
 * Depends on showLoginData object being localized via wp_localize_script.
 */
(function() {
    'use strict';

    // Show popup on page load
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('show-login-overlay');
        if (overlay) {
            overlay.classList.add('show-login-active');
        }
    });

    // Close popup functionality
    const closeBtn = document.getElementById('show-login-close');
    const overlay = document.getElementById('show-login-overlay');

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closePopup();
        });
    }

    // Close on overlay click (not popup content)
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closePopup();
            }
        });
    }

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });

    function closePopup() {
        if (overlay) {
            overlay.classList.remove('show-login-active');
        }
    }

    // Handle form submission
    const form = document.getElementById('show-login-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleLogin();
        });
    }

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

    function showError(message) {
        const errorDiv = document.getElementById('show-login-error');
        errorDiv.innerHTML = message;
        errorDiv.classList.add('show-login-visible');
    }
})();
