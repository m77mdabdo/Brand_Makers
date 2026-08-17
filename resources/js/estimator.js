import Alpine from 'alpinejs';

/**
 * The cost estimator's live total.
 *
 * WHAT THIS IS AND IS NOT. It is a display convenience: it re-adds numbers
 * the server already sent so the figure moves as the visitor ticks boxes,
 * with no round trip. It is NOT the quote. On submission the server
 * recomputes everything from the catalogue and ignores every number on
 * this page — see EstimatorService. Nothing here can change what a lead is
 * recorded as being quoted.
 *
 * The catalogue arrives as a data attribute rather than an inlined script
 * block, so the page carries no executable JSON and the component works
 * from markup Blade escaped normally.
 */

/**
 * Prices are held in HALALAS (integer minor units), not riyals.
 *
 * Floating-point addition of 1200.10 + 2400.20 does not give 3600.30, and
 * an estimator whose total ends in .0000000004 destroys confidence in
 * every other number on the page. The server sends decimal strings; these
 * are parsed to integers once, summed as integers, and divided only for
 * display.
 */
function toMinorUnits(price) {
    const parsed = Number.parseFloat(price);

    return Number.isFinite(parsed) ? Math.round(parsed * 100) : null;
}

function formatMajorUnits(minorUnits, locale) {
    const major = minorUnits / 100;

    // Whole riyals: these are five-figure project estimates, and trailing
    // halalas on a number explicitly labelled an estimate are false
    // precision. A non-whole total still shows its decimals rather than
    // being rounded away silently.
    const fractionDigits = minorUnits % 100 === 0 ? 0 : 2;

    return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(major);
}

Alpine.data('costEstimator', (config = {}) => ({
    /** @type {Record<string, {price: number|null, label: string}>} */
    items: config.items || {},
    /**
     * Services, keyed by SLUG rather than key.
     *
     * A separate map from `items` because the two come from different
     * tables: a service slug and an item key could collide, and one
     * merged map would silently charge whichever landed last.
     *
     * @type {Record<string, {price: number|null, label: string}>}
     */
    services: config.services || {},
    /** @type {Record<string, {price: number|null, name: string}>} */
    packages: config.packages || {},
    locale: config.locale || 'ar',

    /** Rendered strings from the server; the client formats no copy. */
    labels: config.labels || { none: '', selected: '' },

    selectedItems: [],
    selectedServices: [],
    selectedPackage: '',

    get selectedLines() {
        const lines = [];

        const pkg = this.packages[this.selectedPackage];
        if (pkg) {
            lines.push({ id: 'package:' + this.selectedPackage, label: pkg.name, price: pkg.price });
        }

        // Iterate the CATALOGUE, not the selection, so the breakdown is
        // always in catalogue order however the visitor clicked.
        Object.keys(this.services).forEach((slug) => {
            if (this.selectedServices.includes(slug)) {
                lines.push({ id: 'service:' + slug, label: this.services[slug].label, price: this.services[slug].price });
            }
        });

        Object.keys(this.items).forEach((key) => {
            if (this.selectedItems.includes(key)) {
                lines.push({ id: 'item:' + key, label: this.items[key].label, price: this.items[key].price });
            }
        });

        return lines;
    },

    /**
     * The button text for a multi-select group: what is chosen from it,
     * or a prompt when nothing is.
     *
     * The group's own keys are passed in rather than read from a group
     * map, because the template already knows them and threading a second
     * copy of the catalogue through the payload would give two places for
     * the grouping to drift.
     *
     * @param {string[]} keys
     * @param {boolean} isService  read from `services`, not `items`
     */
    groupSummary(keys, isService = false) {
        const selection = isService ? this.selectedServices : this.selectedItems;
        const catalogue = isService ? this.services : this.items;

        const chosen = keys.filter((key) => selection.includes(key));

        if (chosen.length === 0) {
            return this.labels.none;
        }

        if (chosen.length === 1) {
            return catalogue[chosen[0]]?.label ?? this.labels.none;
        }

        return this.labels.selected.replace(':count', String(chosen.length));
    },

    /**
     * Single-answer groups: choosing one option clears the others in that
     * group, so a <select> cannot leave a previous answer counted.
     *
     * @param {string[]} keys
     * @param {string} chosen
     * @param {boolean} isService
     */
    chooseOne(keys, chosen, isService = false) {
        const bucket = isService ? 'selectedServices' : 'selectedItems';

        this[bucket] = this[bucket].filter((key) => ! keys.includes(key));

        if (chosen) {
            this[bucket].push(chosen);
        }
    },

    /** Selected lines that carry no price — on request. */
    get onRequestLines() {
        return this.selectedLines.filter((line) => line.price === null);
    },

    get pricedLines() {
        return this.selectedLines.filter((line) => line.price !== null);
    },

    get hasSelection() {
        return this.selectedLines.length > 0;
    },

    /**
     * True when something selected has no price, so the figure below is a
     * FLOOR rather than a total. The template says "from X" in that case;
     * showing a plain total would quote a number lower than the visitor
     * will actually be charged.
     */
    get isPartial() {
        return this.onRequestLines.length > 0;
    },

    get totalMinorUnits() {
        return this.pricedLines.reduce((sum, line) => sum + line.price, 0);
    },

    get formattedTotal() {
        return formatMajorUnits(this.totalMinorUnits, this.locale);
    },

    formatPrice(price) {
        return price === null ? null : formatMajorUnits(price, this.locale);
    },

    toggleItem(key) {
        const index = this.selectedItems.indexOf(key);

        if (index === -1) {
            this.selectedItems.push(key);
        } else {
            this.selectedItems.splice(index, 1);
        }
    },

    isSelected(key) {
        return this.selectedItems.includes(key);
    },

    reset() {
        this.selectedItems = [];
        this.selectedServices = [];
        this.selectedPackage = '';
    },
}));

/**
 * Reads the catalogue off the root element's data attribute and hands it
 * to the component, converting every price to minor units once.
 */
export function estimatorConfig(element) {
    let raw = {};

    try {
        raw = JSON.parse(element?.dataset?.estimatorCatalogue || '{}');
    } catch {
        // A malformed catalogue leaves the estimator inert rather than
        // throwing: the server-rendered prices are still on the page and
        // the form still submits, which is the behaviour a visitor with
        // JavaScript disabled already gets.
        raw = {};
    }

    const items = {};
    Object.entries(raw.items || {}).forEach(([key, item]) => {
        // null survives as null: an on-request line contributes nothing
        // and must not be coerced to a zero the client then adds up.
        items[key] = { label: item.label, price: item.price === null ? null : toMinorUnits(item.price) };
    });

    const services = {};
    Object.entries(raw.services || {}).forEach(([slug, service]) => {
        services[slug] = { label: service.label, price: service.price === null ? null : toMinorUnits(service.price) };
    });

    const packages = {};
    Object.entries(raw.packages || {}).forEach(([slug, pkg]) => {
        packages[slug] = { name: pkg.name, price: pkg.price === null ? null : toMinorUnits(pkg.price) };
    });

    return { items, services, packages, labels: raw.labels || {}, locale: raw.locale || 'ar' };
}

window.estimatorConfig = estimatorConfig;
