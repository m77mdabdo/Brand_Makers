/**
 * The hero's light-particle canvas and skyline parallax.
 *
 * Everything here is decorative. It is written so that if any of it fails
 * to run — no JS, an old browser, a reduced-motion preference — the hero
 * still renders completely from CSS and the markup underneath, because the
 * canvas sits behind the content and starts empty.
 *
 * THREE THINGS STOP THIS BURNING BATTERY:
 *   1. prefers-reduced-motion is checked before a single frame is drawn,
 *      and re-checked live if the user changes it mid-session.
 *   2. An IntersectionObserver halts the loop once the hero scrolls away —
 *      on a long page that is most of the visit.
 *   3. visibilitychange halts it when the tab is backgrounded.
 */

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

/** Particle count scales with area, so a phone does not run a desktop load. */
function particleCountFor(width, height) {
    return Math.round(Math.min(90, Math.max(18, (width * height) / 24000)));
}

class ParticleField {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d', { alpha: true });
        this.particles = [];
        this.running = false;
        this.frame = null;
        this.lastTime = 0;
        this.visible = true;
        this.inView = true;

        this.motionQuery = window.matchMedia(REDUCED_MOTION);
        this.onResize = this.onResize.bind(this);
        this.tick = this.tick.bind(this);
    }

    start() {
        if (!this.ctx) return;

        this.onResize();
        window.addEventListener('resize', this.onResize, { passive: true });

        // Live preference changes: someone turning reduced-motion ON
        // mid-visit gets an immediately still hero, and turning it off
        // starts the field without a reload.
        this.motionQuery.addEventListener('change', () => this.sync());

        document.addEventListener('visibilitychange', () => {
            this.visible = !document.hidden;
            this.sync();
        });

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(
                ([entry]) => {
                    this.inView = entry.isIntersecting;
                    this.sync();
                },
                { threshold: 0 }
            ).observe(this.canvas);
        }

        this.sync();
    }

    /** The single place that decides whether the loop should be running. */
    sync() {
        const shouldRun = !this.motionQuery.matches && this.visible && this.inView;

        if (shouldRun === this.running) return;

        this.running = shouldRun;

        if (shouldRun) {
            this.lastTime = performance.now();
            this.frame = requestAnimationFrame(this.tick);
        } else {
            cancelAnimationFrame(this.frame);
            // Leave the last frame painted rather than clearing to a hard
            // edge — a reduced-motion user sees a still starfield, not a
            // sudden empty band.
        }
    }

    onResize() {
        const rect = this.canvas.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;

        // Cap the backing store at 2x. Beyond that the cost triples on
        // phones for a difference nobody can see in a blurred glow.
        const dpr = Math.min(window.devicePixelRatio || 1, 2);

        this.canvas.width = Math.round(rect.width * dpr);
        this.canvas.height = Math.round(rect.height * dpr);
        this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        this.width = rect.width;
        this.height = rect.height;
        this.seed();
    }

    seed() {
        const count = particleCountFor(this.width, this.height);

        this.particles = Array.from({ length: count }, () => ({
            x: Math.random() * this.width,
            y: Math.random() * this.height,
            r: Math.random() * 1.6 + 0.4,
            // Slow upward drift with a little lateral wander — embers
            // rising off a city at dusk, not snow falling.
            vx: (Math.random() - 0.5) * 6,
            vy: -(Math.random() * 10 + 4),
            alpha: Math.random() * 0.5 + 0.15,
            phase: Math.random() * Math.PI * 2,
        }));
    }

    tick(now) {
        if (!this.running) return;

        // Delta-timed, and clamped so a backgrounded tab returning does
        // not teleport every particle across the canvas in one frame.
        const dt = Math.min((now - this.lastTime) / 1000, 0.05);
        this.lastTime = now;

        const ctx = this.ctx;
        ctx.clearRect(0, 0, this.width, this.height);

        for (const p of this.particles) {
            p.x += p.vx * dt;
            p.y += p.vy * dt;
            p.phase += dt * 1.6;

            // Wrap rather than respawn, so density stays constant.
            if (p.y < -10) {
                p.y = this.height + 10;
                p.x = Math.random() * this.width;
            }
            if (p.x < -10) p.x = this.width + 10;
            if (p.x > this.width + 10) p.x = -10;

            const twinkle = (Math.sin(p.phase) + 1) / 2;
            ctx.globalAlpha = p.alpha * (0.45 + twinkle * 0.55);
            ctx.fillStyle = twinkle > 0.6 ? '#F6A25A' : '#FAF6F0';
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        }

        ctx.globalAlpha = 1;
        this.frame = requestAnimationFrame(this.tick);
    }
}

/**
 * Two-layer skyline parallax.
 *
 * Transform-only and rAF-throttled, so scrolling never triggers layout.
 * The back layer moves less than the front, which is the whole trick.
 */
function initParallax(root) {
    const layers = root.querySelectorAll('[data-parallax-depth]');
    if (layers.length === 0) return;

    const motionQuery = window.matchMedia(REDUCED_MOTION);
    let ticking = false;

    const apply = () => {
        ticking = false;

        if (motionQuery.matches) {
            layers.forEach((layer) => {
                layer.style.transform = '';
            });
            return;
        }

        const offset = window.scrollY;

        layers.forEach((layer) => {
            const depth = parseFloat(layer.dataset.parallaxDepth) || 0;
            layer.style.transform = `translate3d(0, ${offset * depth}px, 0)`;
        });
    };

    const onScroll = () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(apply);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    motionQuery.addEventListener('change', apply);
    apply();
}

export function initHero() {
    const hero = document.querySelector('[data-hero]');
    if (!hero) return;

    const canvas = hero.querySelector('[data-hero-particles]');
    if (canvas) new ParticleField(canvas).start();

    initParallax(hero);
}
