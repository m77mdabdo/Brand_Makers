/**
 * First-party marketing attribution capture.
 *
 * Runs on every page load and persists two cookies:
 *
 *   cm_first_touch   written ONCE and never overwritten again for the
 *                    life of the cookie — "how did this visitor
 *                    originally find us".
 *   cm_latest_touch  overwritten whenever new tracking parameters appear
 *                    — "what immediately preceded this visit".
 *
 * A page load with NO tracking parameters touches neither cookie, so an
 * organic return visit cannot erase the campaign that originally brought
 * someone in. That asymmetry is the whole point of keeping two.
 *
 * FIRST-PARTY, and deliberately small. These are our own cookies on our
 * own domain carrying values that were already visible in the URL the
 * visitor arrived on — no third-party script, no fingerprinting, no
 * identifier of any kind. SameSite=Lax so they survive a normal
 * click-through from an ad but are not sent on cross-site POSTs.
 *
 * NOT encrypted, on purpose: the forms read them from JavaScript to
 * populate their hidden snapshot fields, and an encrypted cookie is
 * opaque to the client that has to read it. Nothing here is sensitive
 * enough to need it — see bootstrap/app.php's encryptCookies exception
 * list.
 */

const COOKIE_MAX_AGE_DAYS = 30;

const FIRST_TOUCH_COOKIE = 'cm_first_touch';
const LATEST_TOUCH_COOKIE = 'cm_latest_touch';

/**
 * The parameters worth keeping. An allow-list rather than "everything in
 * the query string": storing arbitrary parameters would mean storing
 * whatever anyone appends to a link to us.
 */
const TRACKED_PARAMS = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
    'campaign_id',
    'adset_id',
    'ad_id',
    'gclid',
    'fbclid',
    'ttclid',
];

/** Values longer than this are truncated — a cookie has ~4KB total. */
const MAX_VALUE_LENGTH = 256;

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax${secure}`;
}

function extractTouchFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const touch = {};
    let hasAny = false;

    TRACKED_PARAMS.forEach((key) => {
        const value = params.get(key);

        if (value) {
            touch[key] = value.slice(0, MAX_VALUE_LENGTH);
            hasAny = true;
        }
    });

    if (!hasAny) {
        return null;
    }

    // The PATH only — never the query string. It is the landing page we
    // want, and the query string is where the tracking parameters (and
    // anything else a link carried) live.
    touch.landing_page = window.location.pathname;
    touch.referrer = document.referrer ? document.referrer.slice(0, MAX_VALUE_LENGTH) : null;
    touch.captured_at = new Date().toISOString();

    return touch;
}

function captureAttribution() {
    const touch = extractTouchFromUrl();

    if (!touch) {
        return;
    }

    const payload = JSON.stringify(touch);

    // First touch is write-once. Checking for the cookie rather than
    // tracking "is this a new visitor" keeps the rule simple and correct
    // across tabs and sessions.
    if (!getCookie(FIRST_TOUCH_COOKIE)) {
        setCookie(FIRST_TOUCH_COOKIE, payload, COOKIE_MAX_AGE_DAYS);
    }

    setCookie(LATEST_TOUCH_COOKIE, payload, COOKIE_MAX_AGE_DAYS);
}

function readTouch(cookieName) {
    const raw = getCookie(cookieName);

    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch {
        // A malformed cookie — hand-edited, or truncated by a browser —
        // must not break the form it was going to travel with.
        return null;
    }
}

/**
 * Fills the hidden snapshot inputs every public form carries, so the
 * server receives both touches with the submission.
 *
 * Runs on submit rather than on load: a visitor may sit on a form for a
 * long time, and reading at the last moment means the snapshot reflects
 * whatever is current when they actually send it.
 */
function attachToForms() {
    document.querySelectorAll('form[data-attribution]').forEach((form) => {
        form.addEventListener('submit', () => {
            const first = form.querySelector('input[name="first_touch_snapshot"]');
            const latest = form.querySelector('input[name="latest_touch_snapshot"]');

            if (first) {
                first.value = getCookie(FIRST_TOUCH_COOKIE) ?? '';
            }

            if (latest) {
                latest.value = getCookie(LATEST_TOUCH_COOKIE) ?? '';
            }
        });
    });
}

export function initAttribution() {
    captureAttribution();
    attachToForms();
}

// Exposed for debugging and for anything that needs to read the current
// attribution without a form — read-only, no setters.
window.CreativeMarkAttribution = {
    firstTouch: () => readTouch(FIRST_TOUCH_COOKIE),
    latestTouch: () => readTouch(LATEST_TOUCH_COOKIE),
};
