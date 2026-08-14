import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

import { initAttribution } from './attribution';
// Registers the `bookingPicker` Alpine component and exposes
// bookingConfig() for the booking page's x-data to call.
import './booking';
// Registers the `costEstimator` Alpine component on import, and exposes
// estimatorConfig() on window for the estimator page's x-data to call.
import './estimator';
import { initHero } from './hero';
// Imported for its side effect as well as its export: the module body
// registers the `cookieConsent` Alpine component, and imports are
// evaluated before this file's own statements — so the registration
// lands before Alpine.start() below, which is the only order that works.
import { initVisitorSession } from './visitor-session';

Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();

// Deferred module scripts run after parsing, so the hero markup is already
// in the DOM — no DOMContentLoaded wrapper needed, and starting immediately
// means the canvas is live before the first paint settles.
initHero();
initAttribution();
initVisitorSession();
