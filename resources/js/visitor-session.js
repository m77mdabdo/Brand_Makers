import Alpine from 'alpinejs';

/**
 * Consent, and the time-on-page beacon that depends on it.
 *
 * TWO THINGS LIVE IN ONE FILE because they are the same decision seen from
 * two sides: the banner is where a visitor grants consent, and the beacon
 * is the only client-side thing that consent permits. Splitting them would
 * make it possible to ship one without the other, which is the failure
 * mode worth designing out.
 *
 * The server already records page views on its own (TrackVisitorSession).
 * This file adds the one measurement a server cannot make — how long the
 * page was actually in front of the visitor — and nothing else.
 */

const CONSENT_COOKIE = 'cm_consent';
const CONSENT_GRANTED = 'granted';
const CONSENT_DENIED = 'denied';

/**
 * A year. The banner is an interruption; asking again next week would make
 * it a recurring one, and a visitor who declined has already answered.
 * Declining is stored for exactly as long as accepting.
 */
const CONSENT_DAYS = 365;

/**
 * Below this a "visit" is a bounce or a mis-click and the number is noise.
 * Above it the visitor left the tab open over lunch — capping keeps one
 * abandoned tab from reporting a four-hour reading session.
 */
const MIN_REPORTABLE_MS = 1000;
const MAX_REPORTABLE_MS = 30 * 60 * 1000;

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : null;
}

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax${secure}`;
}

export const consentState = () => getCookie(CONSENT_COOKIE);
export const hasConsented = () => consentState() === CONSENT_GRANTED;

/**
 * Mirrors VisitorTrackingGate::doNotTrackRequested() on the client, so a
 * visitor who has DNT or GPC set is never even shown the banner. Asking
 * someone to opt into what they have already opted out of is a dark
 * pattern, and the server would refuse the request regardless of how they
 * answered.
 */
export function doNotTrackEnabled() {
    return (
        window.doNotTrack === '1' ||
        navigator.doNotTrack === '1' ||
        navigator.doNotTrack === 'yes' ||
        navigator.globalPrivacyControl === true
    );
}

/**
 * Releases the marketing tags the moment the visitor agrees, so a new
 * visitor is tracked from the page they consented on rather than from the
 * next one they happen to load.
 *
 * Both hooks are defined by components/tracking-scripts.blade.php and are
 * absent when the corresponding platform has no ID configured — hence the
 * optional calls rather than assuming either exists.
 *
 *   grantGoogle()   flips Consent Mode v2 from denied to granted. The tag
 *                   is already on the page in its restricted state.
 *   pixels.load()   actually injects Meta and TikTok, which until this
 *                   point have not been fetched at all.
 */
function grantMarketingConsent() {
    window.cmConsent?.grantGoogle?.();
    window.cmPixels?.load?.();
}

/**
 * The banner.
 *
 * `decided` starts true when DNT is on, so the banner never appears — the
 * server refuses to record those requests whatever any cookie says, so
 * there is nothing to ask about.
 */
Alpine.data('cookieConsent', () => ({
    decided: true,

    init() {
        this.decided = doNotTrackEnabled() || consentState() !== null;
    },

    accept() {
        setCookie(CONSENT_COOKIE, CONSENT_GRANTED, CONSENT_DAYS);
        this.decided = true;
        startBeacon();
        grantMarketingConsent();
    },

    /**
     * Declining WRITES A COOKIE rather than doing nothing. The gate treats
     * absence and "denied" identically, so this changes no behaviour on
     * the server — what it buys is not asking again, which is the only
     * thing a visitor who said no actually wants.
     */
    decline() {
        setCookie(CONSENT_COOKIE, CONSENT_DENIED, CONSENT_DAYS);
        this.decided = true;
    },
}));

let beaconStarted = false;
let visibleSince = null;

function accumulate() {
    if (visibleSince === null) {
        return 0;
    }

    const elapsed = Date.now() - visibleSince;
    visibleSince = null;

    return elapsed;
}

/**
 * Reports the time accumulated since the last report.
 *
 * sendBeacon, not fetch: the browser hands the request to the network
 * stack and lets the page die, which is the only way a report survives the
 * navigation that triggered it. fetch() with keepalive is close but is
 * cancelled by some browsers on pagehide.
 *
 * FormData rather than JSON because sendBeacon cannot set headers — so a
 * JSON body could not carry an X-CSRF-TOKEN, whereas a form field named
 * _token is exactly what Laravel's CSRF middleware already reads. The
 * endpoint keeps its CSRF protection instead of being excepted from it.
 */
function report() {
    const elapsed = accumulate();

    if (elapsed < MIN_REPORTABLE_MS || !hasConsented()) {
        return;
    }

    const endpoint = document.querySelector('meta[name="visitor-beacon-url"]')?.content;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!endpoint || !token) {
        return;
    }

    const payload = new FormData();
    payload.append('_token', token);
    payload.append('url', window.location.pathname);
    payload.append('duration_ms', String(Math.round(Math.min(elapsed, MAX_REPORTABLE_MS))));

    navigator.sendBeacon?.(endpoint, payload);
}

export function startBeacon() {
    if (beaconStarted || !hasConsented() || doNotTrackEnabled()) {
        return;
    }

    beaconStarted = true;
    visibleSince = document.visibilityState === 'visible' ? Date.now() : null;

    // visibilitychange covers tab switches and, on mobile, the app being
    // backgrounded — which is how most mobile sessions actually end.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            report();
        } else {
            visibleSince = Date.now();
        }
    });

    // pagehide covers the ordinary case: navigating away or closing the
    // tab. Both can fire for one departure; report() no-ops the second
    // time because accumulate() has already reset the clock.
    window.addEventListener('pagehide', report);
}

export function initVisitorSession() {
    startBeacon();
}
