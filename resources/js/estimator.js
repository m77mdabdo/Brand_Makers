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
    /** @type {Record<string, {price: number|null, name: string}>} */
    packages: config.packages || {},
    locale: config.locale || 'ar',

    selectedItems: [],
    selectedPackage: '',

    get selectedLines() {
        const lines = [];

        const pkg = this.packages[this.selectedPackage];
        if (pkg) {
            lines.push({ id: 'package:' + this.selectedPackage, label: pkg.name, price: pkg.price });
        }

        // Iterate the CATALOGUE, not the selection, so the breakdown is
        // always in catalogue order however the visitor clicked.
        Object.keys(this.items).forEach((key) => {
            if (this.selectedItems.includes(key)) {
                lines.push({ id: 'item:' + key, label: this.items[key].label, price: this.items[key].price });
            }
        });

        return lines;
    },

    /** Selected lines that carry no price — a package on request. */
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
        items[key] = { label: item.label, price: toMinorUnits(item.price) };
    });

    const packages = {};
    Object.entries(raw.packages || {}).forEach(([slug, pkg]) => {
        packages[slug] = { name: pkg.name, price: pkg.price === null ? null : toMinorUnits(pkg.price) };
    });

    return { items, packages, locale: raw.locale || 'ar' };
}

window.estimatorConfig = estimatorConfig;
