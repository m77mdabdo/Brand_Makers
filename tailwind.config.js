import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Creative Mark brand design system.
 *
 * CONTRAST IS PART OF THE PALETTE, NOT AN AFTERTHOUGHT.
 *
 * Three of the brand colours cannot legally carry small text on a light
 * background, so each has an accessible sibling and the comment says which
 * to reach for. Every ratio below was measured against the actual token it
 * sits on, not guessed:
 *
 *   orange       #E86A22   3.22:1 on white — FAILS AA for normal text, and
 *                          white-on-it is 3.22:1 too, so it cannot be a
 *                          button fill either. Decorative only: glows,
 *                          large shapes, icon fills, borders, and display
 *                          text at 24px+ (or 18.66px+ bold) where 3:1 is
 *                          the AA bar.
 *   orange-ink   #B84A15   5.21:1 both ways — on white AND white on it.
 *                          THIS is the token for button fills, small
 *                          orange text, and links on cream.
 *   muted        #7C7166   4.42:1 on cream — just under AA. Borders,
 *                          dividers and decorative icons only.
 *   muted-ink    #6E645A   5.37:1 on cream, 5.78:1 on white. Body copy.
 *   amber        #F6A25A   5.69:1 on petrol. Safe on dark ONLY — 1.9:1 on
 *                          cream, so it must never appear on a light
 *                          surface.
 *
 * The petrols are all comfortable behind white (11.67 / 13.98 / 15.82),
 * and `mist` / `mist-dim` are the on-dark body tones (10.96 / 8.06 on
 * petrol-deep), so the footer and hero never need pure white for
 * secondary copy.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Tajawal is the brand face and reads natively in both
                // scripts, so it leads for Latin as well as Arabic rather
                // than being paired with a second Latin family that would
                // make the English side look like a different site.
                sans: ['Tajawal', ...defaultTheme.fontFamily.sans],
            },

            colors: {
                petrol: '#123C54',
                'petrol-2': '#0D2F42',
                'petrol-deep': '#0A2534',

                // Decorative — never small text on light, never a fill
                // behind white text. See the class docblock.
                orange: '#E86A22',
                'orange-deep': '#C8541A',
                // The accessible orange. Button fills and small text.
                'orange-ink': '#B84A15',
                'orange-ink-hover': '#8C370D',

                // On-dark accent only.
                amber: '#F6A25A',

                cream: '#FAF6F0',
                'cream-2': '#F0E7DB',
                line: '#E3D8C8',

                ink: '#16110D',
                // Decorative only (borders, icons).
                muted: '#7C7166',
                // Body copy on light.
                'muted-ink': '#6E645A',

                // Body copy on petrol.
                mist: '#CBD9E1',
                'mist-dim': '#A9BCC6',
            },

            maxWidth: {
                wrap: '1200px',
            },

            borderRadius: {
                '4xl': '2rem',
            },

            keyframes: {
                // The hero's breathing sun. Opacity and scale only, so it
                // composites on the GPU and never triggers layout.
                'sun-breathe': {
                    '0%, 100%': { opacity: '0.5', transform: 'translateX(-50%) scale(1)' },
                    '50%': { opacity: '0.85', transform: 'translateX(-50%) scale(1.06)' },
                },
                // Clouds drift across and wrap. Two layers run this at
                // different durations to fake depth.
                'cloud-drift': {
                    from: { transform: 'translate3d(0, 0, 0)' },
                    to: { transform: 'translate3d(-50%, 0, 0)' },
                },
                'scroll-cue': {
                    '0%, 100%': { transform: 'translate(-50%, 0)' },
                    '50%': { transform: 'translate(-50%, 8px)' },
                },
                // Window lights. Staggered per-element via animation-delay
                // so a row of windows does not blink in unison.
                twinkle: {
                    '0%, 100%': { opacity: '0.25' },
                    '50%': { opacity: '1' },
                },
                'marquee-ltr': {
                    from: { transform: 'translateX(0)' },
                    to: { transform: 'translateX(-50%)' },
                },
                'marquee-rtl': {
                    from: { transform: 'translateX(0)' },
                    to: { transform: 'translateX(50%)' },
                },
            },

            animation: {
                'sun-breathe': 'sun-breathe 7s ease-in-out infinite',
                'cloud-slow': 'cloud-drift 90s linear infinite',
                'cloud-fast': 'cloud-drift 55s linear infinite',
                'scroll-cue': 'scroll-cue 1.8s ease-in-out infinite',
                twinkle: 'twinkle 4s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
