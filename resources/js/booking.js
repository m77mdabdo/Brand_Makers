import Alpine from 'alpinejs';

/**
 * The date and slot picker.
 *
 * PURELY A VIEW over availability the server already generated. It filters
 * a list it was handed; it never decides what is bookable, and it is not
 * where the chosen pair is stored either — the day and time controls are
 * native radios, so the browser handles selection, keyboard navigation and
 * form submission. On submission the server re-derives the whole slot list
 * and looks the pair up in it, so nothing here can produce an appointment
 * the studio did not offer.
 *
 * That is also why there is no fetch: the whole window is a few dozen
 * days, it is already on the page, and a request per date would put a
 * round trip between tapping a day and seeing its times.
 */

/**
 * Availability is sparse — closed weekdays, blackout dates, days inside
 * the notice period and full slots are all absent from the list the
 * server sends — so the picker renders exactly what it was given rather
 * than a calendar grid with holes in it.
 */
Alpine.data('bookingPicker', (config = {}) => ({
    days: config.days || [],
    selectedDate: config.selectedDate || '',
    selectedTime: config.selectedTime || '',

    init() {
        // A date restored from old input may no longer be offered — the
        // slot could have filled while the visitor was typing. Clearing
        // it here means the summary never shows a time the form would be
        // rejected for.
        if (this.selectedDate && !this.dayFor(this.selectedDate)) {
            this.selectedDate = '';
            this.selectedTime = '';
        }
    },

    dayFor(date) {
        return this.days.find((day) => day.date === date) || null;
    },

    get selectedDay() {
        return this.dayFor(this.selectedDate);
    },

    get slots() {
        return this.selectedDay ? this.selectedDay.slots : [];
    },

    get hasSelection() {
        return Boolean(this.selectedDate && this.selectedTime);
    },

    /** The chosen day and time, for the summary line. */
    get summary() {
        if (!this.hasSelection) {
            return '';
        }

        return `${this.selectedDay.label} · ${this.selectedTime}`;
    },

    /**
     * Clears the chosen time when the day changes.
     *
     * The day and time controls are native radios bound with x-model, so
     * Alpine already has the new date by the time this fires — the only
     * thing left to do is drop a time that belonged to the previous day.
     * Times differ per day, so keeping it would submit a pair the server
     * rejects and the visitor would be told to pick again for no visible
     * reason.
     */
    onDateChange() {
        this.selectedTime = '';
    },
}));

/**
 * Reads the availability off the root element's data attribute.
 *
 * A data attribute rather than an inline <script>: the page then carries
 * no executable JSON, and Blade's ordinary attribute escaping is doing the
 * work.
 */
export function bookingConfig(element) {
    try {
        return JSON.parse(element?.dataset?.bookingAvailability || '{}');
    } catch {
        // A malformed payload leaves the picker inert rather than
        // throwing. The server-rendered <noscript> fallback list is still
        // on the page, so the form remains usable.
        return { days: [] };
    }
}

window.bookingConfig = bookingConfig;
