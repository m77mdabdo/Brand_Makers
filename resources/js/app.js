import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

import { initAttribution } from './attribution';
import { initHero } from './hero';

Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();

// Deferred module scripts run after parsing, so the hero markup is already
// in the DOM — no DOMContentLoaded wrapper needed, and starting immediately
// means the canvas is live before the first paint settles.
initHero();
initAttribution();
