<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The palette's accessibility guarantees, as executable assertions.
 *
 * Three brand colours cannot legally carry small text on a light
 * background, and each has an accessible sibling that must be used
 * instead (see tailwind.config.js). Those substitutions are easy to undo
 * by accident — "it looked better in the brighter orange" — and the damage
 * is invisible to anyone not running a contrast checker.
 *
 * So the pairings are pinned here: the accessible tokens must PASS, and
 * the decorative ones must still FAIL, because a decorative token that
 * quietly starts passing means someone changed the brand colour rather
 * than the usage.
 */
class BrandContrastTest extends TestCase
{
    private const AA_NORMAL = 4.5;

    private const AA_LARGE = 3.0;

    // --- brand tokens, mirroring tailwind.config.js -------------------
    private const PETROL = '#123C54';

    private const PETROL_DEEP = '#0A2534';

    private const ORANGE = '#E86A22';

    private const ORANGE_INK = '#B84A15';

    private const ORANGE_INK_HOVER = '#8C370D';

    private const AMBER = '#F6A25A';

    private const CREAM = '#FAF6F0';

    private const INK = '#16110D';

    private const MUTED = '#7C7166';

    private const MUTED_INK = '#6E645A';

    private const MIST = '#CBD9E1';

    private const MIST_DIM = '#A9BCC6';

    private const WHITE = '#FFFFFF';

    /**
     * Every pairing the site actually renders, and the bar it has to clear.
     *
     * @return array<string, array{string, string, float}>
     */
    public static function requiredPairs(): array
    {
        return [
            // Body copy on light surfaces.
            'ink on cream' => [self::INK, self::CREAM, self::AA_NORMAL],
            'muted-ink on cream (body copy)' => [self::MUTED_INK, self::CREAM, self::AA_NORMAL],
            'muted-ink on white (card copy)' => [self::MUTED_INK, self::WHITE, self::AA_NORMAL],

            // The accessible orange, both ways round: as text on light, and
            // as the fill behind a white button label.
            'orange-ink on cream' => [self::ORANGE_INK, self::CREAM, self::AA_NORMAL],
            'orange-ink on white (eyebrow, badge)' => [self::ORANGE_INK, self::WHITE, self::AA_NORMAL],
            'white on orange-ink (primary button)' => [self::WHITE, self::ORANGE_INK, self::AA_NORMAL],
            'white on orange-ink-hover (button hover)' => [self::WHITE, self::ORANGE_INK_HOVER, self::AA_NORMAL],

            // On petrol.
            'white on petrol' => [self::WHITE, self::PETROL, self::AA_NORMAL],
            'white on petrol-deep' => [self::WHITE, self::PETROL_DEEP, self::AA_NORMAL],
            'amber on petrol (hero highlight)' => [self::AMBER, self::PETROL, self::AA_NORMAL],
            'mist on petrol-deep (footer copy)' => [self::MIST, self::PETROL_DEEP, self::AA_NORMAL],
            'mist-dim on petrol-deep (footer copy)' => [self::MIST_DIM, self::PETROL_DEEP, self::AA_NORMAL],

            // The hero's readability overlay is what makes this hold no
            // matter what ends up behind it — including a video later.
            'hero lead copy on the overlaid sky' => ['#E0E9EF', '#0F3247', self::AA_NORMAL],
        ];
    }

    #[DataProvider('requiredPairs')]
    public function test_the_pairing_meets_wcag_aa(string $foreground, string $background, float $minimum): void
    {
        $ratio = $this->contrast($foreground, $background);

        $this->assertGreaterThanOrEqual(
            $minimum,
            $ratio,
            "{$foreground} on {$background} is {$ratio}:1, below the required {$minimum}:1."
        );
    }

    /**
     * The decorative tokens. If one of these starts PASSING, the brand
     * colour itself has been changed — which is a decision worth making
     * deliberately, not something that should slip through because a
     * contrast test only ever checked the happy direction.
     *
     * @return array<string, array{string, string}>
     */
    public static function decorativeOnlyPairs(): array
    {
        return [
            'brand orange on cream' => [self::ORANGE, self::CREAM],
            'white on brand orange' => [self::WHITE, self::ORANGE],
            'muted on cream' => [self::MUTED, self::CREAM],
        ];
    }

    #[DataProvider('decorativeOnlyPairs')]
    public function test_the_decorative_token_is_still_unsafe_for_small_text(string $foreground, string $background): void
    {
        $this->assertLessThan(
            self::AA_NORMAL,
            $this->contrast($foreground, $background),
            "{$foreground} on {$background} now passes AA. If the brand colour changed, move it out of the ".
            'decorative-only group in tailwind.config.js and update the usage rules with it.'
        );
    }

    /**
     * The brand orange is still fine for LARGE display text, which is the
     * only place the views use it on a light surface.
     */
    public function test_the_brand_orange_remains_usable_for_large_display_text(): void
    {
        $this->assertGreaterThanOrEqual(self::AA_LARGE, $this->contrast(self::ORANGE, self::WHITE));
    }

    private function contrast(string $foreground, string $background): float
    {
        $lighter = max($this->luminance($foreground), $this->luminance($background));
        $darker = min($this->luminance($foreground), $this->luminance($background));

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
