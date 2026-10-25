/**
 * Tests for assets/js/show-login.js.
 *
 * The script is an IIFE that runs on load, so each test sets up the page
 * (URL, showLoginData, fetch, Turnstile), loads a fresh copy and fires
 * DOMContentLoaded.
 */

const SCRIPT = '../../assets/js/show-login.js';

// Mirrors the markup templates/popup.php renders.
const POPUP_HTML = `
<div id="show-login-overlay">
    <div id="show-login-popup">
        <button id="show-login-close" type="button" aria-label="Close">&times;</button>
        <h2>Log In</h2>
        <div id="show-login-error" role="alert"></div>
        <form id="show-login-form" method="post" novalidate>
            <input type="text" id="show-login-username" name="username" />
            <input type="password" id="show-login-password" name="password" />
            <input type="checkbox" id="show-login-remember" name="remember" value="1" />
            <button type="submit" id="show-login-submit">Log In</button>
        </form>
    </div>
</div>`;

const TURNSTILE_WIDGET = '<div class="cf-turnstile" id="cf-turnstile-show-login" data-sitekey="site-key"></div>';

const popupWith = (extra) => POPUP_HTML.replace('<button type="submit"', extra + '<button type="submit"');

const json = (body) => ({
    headers: { get: () => 'application/json; charset=UTF-8' },
    json: () => Promise.resolve(body),
});

const loggedOut = (html = POPUP_HTML) => json({
    success: true,
    data: { show: true, html, nonce: 'nonce-123', redirectUrl: 'http://example.org/page/' },
});

const loggedIn = () => json({ success: true, data: { show: false, reason: 'already_logged_in' } });

const $ = (selector) => document.querySelector(selector);

// Stands in for wp.i18n.__ (WordPress loads the real one as the wp-i18n dependency).
// Returns the English text unless a test sets a translation.
let translations = {};
const translate = (text, domain) => {
    expect(domain).toBe('show-login');
    return translations[text] ?? text;
};

const originalLocation = window.location;
let listeners;

function setUrl(search) {
    delete window.location;
    window.location = { search, href: 'http://example.org/page/' + search };
}

/**
 * Loads the script and fires DOMContentLoaded.
 *
 * The script injects the Turnstile API and waits for it before checking login
 * status. Unless holdScript is set, we pretend that script loaded right away.
 */
function boot({ search = '?sl=1', suppressLoading = false, turnstile = false, holdScript = false } = {}) {
    setUrl(search);
    window.showLoginData = { ajaxUrl: '/wp-admin/admin-ajax.php', suppressLoading, turnstile };
    window.wp = { i18n: { __: translate } };
    jest.isolateModules(() => {
        require(SCRIPT);
    });
    document.dispatchEvent(new Event('DOMContentLoaded'));

    const injected = document.head.querySelector('script[src*="render=explicit"]');
    if (injected && !holdScript) {
        injected.onload();
    }
}

/**
 * Boots with the given popup response and waits until the form is on the page.
 */
async function bootToForm(html = POPUP_HTML, { turnstile = false } = {}) {
    global.fetch.mockResolvedValueOnce(loggedOut(html));
    boot({ suppressLoading: true, turnstile });
    await jest.advanceTimersByTimeAsync(0);
    expect($('#show-login-form')).not.toBeNull();
}

function fillAndSubmit({ username = 'alice', password = 'secret', remember = false } = {}) {
    $('#show-login-username').value = username;
    $('#show-login-password').value = password;
    $('#show-login-remember').checked = remember;
    $('#show-login-form').dispatchEvent(new Event('submit', { cancelable: true }));
}

const lastFetchBody = () => global.fetch.mock.calls[global.fetch.mock.calls.length - 1][1].body;

beforeEach(() => {
    jest.useFakeTimers();
    document.head.innerHTML = '';
    document.body.innerHTML = '';
    delete window.turnstile;
    delete window.showLoginTurnstileCallback;
    global.fetch = jest.fn();
    translations = {};

    // Track listeners the script adds to document so each test starts clean.
    listeners = [];
    const add = EventTarget.prototype.addEventListener;
    jest.spyOn(document, 'addEventListener').mockImplementation(function (type, fn, options) {
        listeners.push([type, fn, options]);
        return add.call(this, type, fn, options);
    });
});

afterEach(() => {
    listeners.forEach(([type, fn, options]) => document.removeEventListener(type, fn, options));
    jest.restoreAllMocks();
    jest.useRealTimers();
    window.location = originalLocation;
});

describe('trigger parameter', () => {
    test.each(['?sl=1', '?sl=true', '?show_login=1', '?show_login=true', '?a=b&sl=1'])('%s opens the popup', (search) => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ search });

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test.each(['', '?sl=0', '?sl=yes', '?show_login=false', '?login=1'])('"%s" does nothing', (search) => {
        boot({ search });

        expect(global.fetch).not.toHaveBeenCalled();
        expect($('#show-login-overlay')).toBeNull();
    });
});

describe('checking login status', () => {
    test('posts the action and current URL', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ search: '?sl=1&x=a b' });

        expect(global.fetch).toHaveBeenCalledWith('/wp-admin/admin-ajax.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=show_login_check_popup&current_url=' + encodeURIComponent('http://example.org/page/?sl=1&x=a b'),
        });
    });

    test('shows a spinner straight away', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot();

        expect($('#show-login-overlay').classList.contains('show-login-active')).toBe(true);
        expect($('.show-login-loading-state').textContent).toContain('Checking login status...');
    });

    test('no spinner when loading state is suppressed', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ suppressLoading: true });

        expect($('#show-login-overlay')).toBeNull();
    });

    test('logged-in users see a message, then the popup closes', async () => {
        global.fetch.mockResolvedValue(loggedIn());

        boot();
        await jest.advanceTimersByTimeAsync(1000);

        expect($('.show-login-loading-state').textContent).toContain("You're already logged in!");

        await jest.advanceTimersByTimeAsync(1000);
        expect($('#show-login-overlay').classList.contains('show-login-active')).toBe(false);

        await jest.advanceTimersByTimeAsync(300);
        expect($('#show-login-overlay')).toBeNull();
    });

    test('logged-in users see nothing when loading state is suppressed', async () => {
        global.fetch.mockResolvedValue(loggedIn());

        boot({ suppressLoading: true });
        await jest.advanceTimersByTimeAsync(2000);

        expect(document.body.innerHTML).toBe('');
    });

    test('a failed response is treated as logged in', async () => {
        global.fetch.mockResolvedValue(json({ success: false, data: {} }));

        boot();
        await jest.advanceTimersByTimeAsync(1000);

        expect($('.show-login-loading-state').textContent).toContain("You're already logged in!");
    });

    test('logged-out users see a message, then the form', async () => {
        global.fetch.mockResolvedValue(loggedOut());

        boot();
        await jest.advanceTimersByTimeAsync(1000);

        expect($('.show-login-loading-state').textContent).toContain("You're not logged in");
        expect($('#show-login-form')).toBeNull();

        await jest.advanceTimersByTimeAsync(1000);

        expect($('.show-login-loading-state')).toBeNull();
        expect(document.querySelectorAll('#show-login-overlay')).toHaveLength(1);
        expect($('#show-login-overlay').classList.contains('show-login-active')).toBe(true);
        expect($('#show-login-form')).not.toBeNull();
        expect(window.showLoginData.nonce).toBe('nonce-123');
        expect(window.showLoginData.redirectUrl).toBe('http://example.org/page/');
    });

    test('form still appears if the spinner was removed meanwhile', async () => {
        global.fetch.mockResolvedValue(loggedOut());

        boot();
        $('#show-login-overlay').remove();
        await jest.advanceTimersByTimeAsync(2000);

        expect($('#show-login-form')).not.toBeNull();
    });

    test('the spinner stays up for at least a second, even if the server answers at once', async () => {
        global.fetch.mockResolvedValue(loggedOut());

        boot();
        await jest.advanceTimersByTimeAsync(999);

        expect($('.show-login-loading-state').textContent).toContain('Checking login status...');

        await jest.advanceTimersByTimeAsync(1);
        expect($('.show-login-loading-state').textContent).toContain("You're not logged in");
    });

    test('a slow server adds no extra spinner time', async () => {
        let respond;
        global.fetch.mockReturnValue(new Promise((resolve) => { respond = resolve; }));

        boot();
        await jest.advanceTimersByTimeAsync(1500);
        respond(loggedOut());
        await jest.advanceTimersByTimeAsync(0);

        expect($('.show-login-loading-state').textContent).toContain("You're not logged in");
    });

    test('logged-out users get the form at once when loading state is suppressed', async () => {
        await bootToForm();

        expect($('#show-login-overlay').classList.contains('show-login-active')).toBe(true);
        expect(window.showLoginData.nonce).toBe('nonce-123');
    });

    test('a network error closes the spinner', async () => {
        global.fetch.mockRejectedValue(new Error('offline'));

        boot();
        await jest.advanceTimersByTimeAsync(300);

        expect($('#show-login-overlay')).toBeNull();
    });

    test('a broken response closes the spinner instead of leaving it stuck', async () => {
        global.fetch.mockResolvedValue(json(null));

        boot();
        await jest.advanceTimersByTimeAsync(1300);

        expect($('#show-login-overlay')).toBeNull();
    });

    test('a network error with no spinner does nothing', async () => {
        global.fetch.mockRejectedValue(new Error('offline'));

        boot({ suppressLoading: true });
        await jest.advanceTimersByTimeAsync(300);

        expect(document.body.innerHTML).toBe('');
    });

    test('a server response without matching markup does not crash', async () => {
        global.fetch.mockResolvedValue(loggedOut('<p>custom</p>'));

        boot({ suppressLoading: true });
        await jest.advanceTimersByTimeAsync(0);

        expect(document.body.innerHTML).toContain('<p>custom</p>');
    });
});

describe('closing the popup', () => {
    test('close button', async () => {
        await bootToForm();

        $('#show-login-close').click();
        await jest.advanceTimersByTimeAsync(300);

        expect($('#show-login-overlay')).toBeNull();
    });

    test('Escape key', async () => {
        await bootToForm();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await jest.advanceTimersByTimeAsync(300);

        expect($('#show-login-overlay')).toBeNull();
    });

    test('other keys do nothing', async () => {
        await bootToForm();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        await jest.advanceTimersByTimeAsync(300);

        expect($('#show-login-overlay')).not.toBeNull();
    });
});

describe('logging in', () => {
    test('blank fields show an error without sending anything', async () => {
        await bootToForm();
        global.fetch.mockClear();

        fillAndSubmit({ password: '' });

        expect(global.fetch).not.toHaveBeenCalled();
        expect($('#show-login-error').textContent).toBe('Please enter both username and password.');
        expect($('#show-login-error').classList.contains('show-login-visible')).toBe(true);
    });

    test('sends the form, nonce and redirect, then goes to the redirect URL', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({ success: true, data: { message: 'ok' } }));

        fillAndSubmit({ remember: true });

        expect($('#show-login-submit').disabled).toBe(true);
        expect($('#show-login-submit').classList.contains('show-login-loading')).toBe(true);

        const body = lastFetchBody();
        expect(body.get('action')).toBe('show_login_authenticate');
        expect(body.get('nonce')).toBe('nonce-123');
        expect(body.get('redirect_to')).toBe('http://example.org/page/');
        expect(body.get('username')).toBe('alice');
        expect(body.get('password')).toBe('secret');
        expect(body.get('remember')).toBe('1');

        await jest.advanceTimersByTimeAsync(0);
        expect(window.location.href).toBe('http://example.org/page/');
    });

    test('unchecked remember-me is not sent', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({ success: true }));

        fillAndSubmit();

        expect(lastFetchBody().has('remember')).toBe(false);
    });

    test('server errors are shown and the button comes back', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { message: '<strong>Error:</strong> Invalid username or password.' } }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').innerHTML).toBe('<strong>Error:</strong> Invalid username or password.');
        expect($('#show-login-submit').disabled).toBe(false);
        expect($('#show-login-submit').classList.contains('show-login-loading')).toBe(false);
    });

    test('a previous error is cleared on the next try', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { message: 'Bad.' } }));
        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        global.fetch.mockReturnValueOnce(new Promise(() => {}));
        fillAndSubmit();

        expect($('#show-login-error').innerHTML).toBe('');
        expect($('#show-login-error').classList.contains('show-login-visible')).toBe(false);
    });

    test('errors without a message get a fallback', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({ success: false, data: {} }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('Login failed. Please try again.');
    });

    test('two-factor users are sent to the 2FA form', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({
            success: false,
            data: { two_factor_required: true, redirect_url: 'http://example.org/wp-login.php?action=validate_2fa' },
        }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect(window.location.href).toBe('http://example.org/wp-login.php?action=validate_2fa');
        expect($('#show-login-error').textContent).toBe('');
    });

    test('a 2FA flag without a URL is shown as an error', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { two_factor_required: true, message: 'Hmm.' } }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('Hmm.');
    });

    describe('Wordfence two-factor code', () => {
        // Mirrors Show_Login_Wordfence::render_code_field().
        const CODE_FIELD = '<div class="show-login-field" id="show-login-2fa-field" hidden>'
            + '<input type="text" id="show-login-2fa-code" name="wfls-token" disabled /></div>';
        const codeRequired = () => json({ success: false, data: { code_required: true, message: 'Enter the code from your authenticator app.' } });

        test('a code request shows the code field', async () => {
            await bootToForm(popupWith(CODE_FIELD));
            global.fetch.mockResolvedValueOnce(codeRequired());

            fillAndSubmit();
            await jest.advanceTimersByTimeAsync(0);

            expect($('#show-login-2fa-field').hidden).toBe(false);
            expect($('#show-login-2fa-code').disabled).toBe(false);
            expect(document.activeElement).toBe($('#show-login-2fa-code'));
            expect($('#show-login-error').textContent).toBe('Enter the code from your authenticator app.');
            expect($('#show-login-submit').disabled).toBe(false);
        });

        test('the code is sent with the next try', async () => {
            await bootToForm(popupWith(CODE_FIELD));
            global.fetch.mockResolvedValueOnce(codeRequired());
            fillAndSubmit();
            await jest.advanceTimersByTimeAsync(0);

            global.fetch.mockResolvedValueOnce(json({ success: true, data: {} }));
            $('#show-login-2fa-code').value = '123456';
            fillAndSubmit();
            await jest.advanceTimersByTimeAsync(0);

            expect(lastFetchBody().get('wfls-token')).toBe('123456');
            expect(window.location.href).toBe('http://example.org/page/');
        });

        test('the hidden code field is not sent with normal logins', async () => {
            await bootToForm(popupWith(CODE_FIELD));
            global.fetch.mockResolvedValueOnce(json({ success: true, data: {} }));

            fillAndSubmit();
            await jest.advanceTimersByTimeAsync(0);

            expect(lastFetchBody().has('wfls-token')).toBe(false);
        });

        test('a code request without the field still shows the message', async () => {
            await bootToForm();
            global.fetch.mockResolvedValueOnce(codeRequired());

            fillAndSubmit();
            await jest.advanceTimersByTimeAsync(0);

            expect($('#show-login-error').textContent).toBe('Enter the code from your authenticator app.');
        });
    });

    test('non-JSON responses show a generic error', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce({ headers: { get: () => 'text/html' } });

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('An error occurred. Please try again.');
        expect($('#show-login-submit').disabled).toBe(false);
    });

    test('responses with no content type show a generic error', async () => {
        await bootToForm();
        global.fetch.mockResolvedValueOnce({ headers: { get: () => null } });

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('An error occurred. Please try again.');
    });

    test('network failures show a generic error', async () => {
        await bootToForm();
        global.fetch.mockRejectedValueOnce(new Error('offline'));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('An error occurred. Please try again.');
    });
});

describe('Turnstile', () => {
    function fakeTurnstile() {
        return { render: jest.fn(), reset: jest.fn(), remove: jest.fn() };
    }

    test('never loads Cloudflare\'s script when the site does not use Turnstile', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ turnstile: false, holdScript: true });

        expect(document.head.querySelector('script')).toBeNull();
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('loads the Turnstile script before checking login status', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ turnstile: true, holdScript: true });

        const script = document.head.querySelector('script');
        expect(script.src).toBe('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit');
        expect(script.async).toBe(true);
        expect(script.getAttribute('data-cfasync')).toBe('false');
        expect(global.fetch).not.toHaveBeenCalled();

        script.onload();
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('carries on if the Turnstile script fails to load', () => {
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ turnstile: true, holdScript: true });
        document.head.querySelector('script').onerror();

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('waits for a Turnstile script that is already loading', async () => {
        document.head.innerHTML = '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>';
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot({ turnstile: true });
        await jest.advanceTimersByTimeAsync(300);
        expect(global.fetch).not.toHaveBeenCalled();
        expect(document.head.querySelectorAll('script')).toHaveLength(1);

        window.turnstile = fakeTurnstile();
        await jest.advanceTimersByTimeAsync(100);
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    test('renders the widget with its site key', async () => {
        window.turnstile = fakeTurnstile();

        await bootToForm(popupWith(TURNSTILE_WIDGET), { turnstile: true });

        expect(window.turnstile.render).toHaveBeenCalledWith('#cf-turnstile-show-login', { sitekey: 'site-key' });
    });

    test('does not render a widget twice', async () => {
        window.turnstile = fakeTurnstile();

        await bootToForm(popupWith(TURNSTILE_WIDGET.replace('></div>', '><iframe></iframe></div>')), { turnstile: true });

        expect(window.turnstile.render).not.toHaveBeenCalled();
    });

    test('does nothing when the popup has no widget', async () => {
        window.turnstile = fakeTurnstile();

        await bootToForm(POPUP_HTML, { turnstile: true });

        expect(window.turnstile.render).not.toHaveBeenCalled();
    });

    test('a render error does not break the popup', async () => {
        window.turnstile = fakeTurnstile();
        window.turnstile.render.mockImplementation(() => {
            throw new Error('boom');
        });

        await bootToForm(popupWith(TURNSTILE_WIDGET), { turnstile: true });

        expect($('#show-login-overlay').classList.contains('show-login-active')).toBe(true);
    });

    test('submits straight away when the token is already there', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(popupWith(TURNSTILE_WIDGET + '<input type="hidden" name="cf-turnstile-response" value="tok-1" />'), { turnstile: true });
        global.fetch.mockReturnValueOnce(new Promise(() => {}));

        fillAndSubmit();

        expect(lastFetchBody().getAll('cf-turnstile-response')).toContain('tok-1');
    });

    test('sends a token that lives outside the form', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(popupWith(TURNSTILE_WIDGET), { turnstile: true });
        document.body.insertAdjacentHTML('beforeend', '<input type="hidden" name="cf-turnstile-response" value="tok-out" />');
        global.fetch.mockReturnValueOnce(new Promise(() => {}));

        fillAndSubmit();

        expect(lastFetchBody().get('cf-turnstile-response')).toBe('tok-out');
    });

    test('waits for the token, then submits', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(popupWith(TURNSTILE_WIDGET + '<input type="hidden" name="cf-turnstile-response" value="" />'), { turnstile: true });
        global.fetch.mockClear();
        global.fetch.mockReturnValue(new Promise(() => {}));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(500);
        expect(global.fetch).not.toHaveBeenCalled();

        document.querySelector('input[name="cf-turnstile-response"]').value = 'tok-late';
        await jest.advanceTimersByTimeAsync(100);

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(lastFetchBody().getAll('cf-turnstile-response')).toContain('tok-late');
    });

    test('submits anyway after 3 seconds without a token', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(popupWith(TURNSTILE_WIDGET), { turnstile: true });
        global.fetch.mockClear();
        global.fetch.mockReturnValue(new Promise(() => {}));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(2900);
        expect(global.fetch).not.toHaveBeenCalled();

        await jest.advanceTimersByTimeAsync(100);
        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(lastFetchBody().has('cf-turnstile-response')).toBe(false);
    });

    test('resets the widget after a failed login (tokens are single-use)', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(popupWith(TURNSTILE_WIDGET + '<input type="hidden" name="cf-turnstile-response" value="tok" />'), { turnstile: true });
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { message: 'Bad.' } }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect(window.turnstile.reset).toHaveBeenCalledWith($('.cf-turnstile'));
    });

    test('re-renders the widget if reset fails', async () => {
        window.turnstile = fakeTurnstile();
        window.turnstile.reset.mockImplementation(() => {
            throw new Error('nope');
        });
        await bootToForm(popupWith(TURNSTILE_WIDGET + '<input type="hidden" name="cf-turnstile-response" value="tok" />'), { turnstile: true });
        window.turnstile.render.mockClear();
        global.fetch.mockRejectedValueOnce(new Error('offline'));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect(window.turnstile.remove).toHaveBeenCalledWith('#cf-turnstile-show-login');
        expect(window.turnstile.render).toHaveBeenCalledWith('#cf-turnstile-show-login', { sitekey: 'site-key' });
    });

    test('gives up quietly if re-rendering fails too', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(popupWith(TURNSTILE_WIDGET + '<input type="hidden" name="cf-turnstile-response" value="tok" />'), { turnstile: true });
        window.turnstile.reset.mockImplementation(() => {
            throw new Error('nope');
        });
        window.turnstile.remove.mockImplementation(() => {
            throw new Error('still nope');
        });
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { message: 'Bad.' } }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('Bad.');
    });

    test('no reset when the popup has no widget', async () => {
        window.turnstile = fakeTurnstile();
        await bootToForm(POPUP_HTML, { turnstile: true });
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { message: 'Bad.' } }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect(window.turnstile.reset).not.toHaveBeenCalled();
    });

    test('no reset when Turnstile is not loaded', async () => {
        await bootToForm(popupWith(TURNSTILE_WIDGET), { turnstile: true });
        global.fetch.mockResolvedValueOnce(json({ success: false, data: { message: 'Bad.' } }));

        fillAndSubmit();
        await jest.advanceTimersByTimeAsync(0);

        expect($('#show-login-error').textContent).toBe('Bad.');
    });

    test('the Turnstile callback re-enables the submit button', async () => {
        await bootToForm(POPUP_HTML, { turnstile: true });
        const button = $('#show-login-submit');
        button.disabled = true;
        button.style.pointerEvents = 'none';
        button.style.opacity = '0.5';

        window.showLoginTurnstileCallback();

        expect(button.disabled).toBe(false);
        expect(button.style.pointerEvents).toBe('');
        expect(button.style.opacity).toBe('');
    });

    test('the Turnstile callback is safe without a popup', () => {
        boot({ search: '' });

        expect(() => window.showLoginTurnstileCallback()).not.toThrow();
    });
});

describe('translations', () => {
    test('status messages use the translated text from wp.i18n', () => {
        translations = {
            'Checking login status...': 'Anmeldestatus wird geprüft...',
        };
        global.fetch.mockReturnValue(new Promise(() => {}));

        boot();

        expect($('.show-login-loading-state').textContent).toContain('Anmeldestatus wird geprüft...');
    });

    test('form errors use the translated text from wp.i18n', async () => {
        translations = { 'Please enter both username and password.': 'Bitte Benutzername und Passwort eingeben.' };
        await bootToForm();

        fillAndSubmit({ password: '' });

        expect($('#show-login-error').textContent).toBe('Bitte Benutzername und Passwort eingeben.');
    });
});
