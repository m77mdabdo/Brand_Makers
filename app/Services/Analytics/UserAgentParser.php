<?php

namespace App\Services\Analytics;

/**
 * Coarse device / browser / platform from a user agent string, plus bot
 * detection.
 *
 * WHY NOT A LIBRARY. The mature options (matomo/device-detector,
 * jenssegers/agent) carry thousands of regexes and answer questions this
 * project never asks — exact model, browser version, bot vendor. What is
 * needed here is three buckets for a dashboard column, and the parse runs
 * on every tracked page view. The trade is the long tail: an obscure
 * browser reports as null rather than by name, which is the correct
 * failure — nothing downstream branches on this value.
 *
 * ORDER MATTERS in both maps below and is the only subtle part:
 *   - Edge and Opera both carry "Chrome" in their UA and Chrome carries
 *     "Safari", so the specific names have to be tested first.
 *   - iPadOS reports itself as "Macintosh", so the iPad test precedes the
 *     Mac one.
 * Each map is ordered most-specific-first. Do not sort them.
 */
class UserAgentParser
{
    public const DEVICE_MOBILE = 'mobile';

    public const DEVICE_TABLET = 'tablet';

    public const DEVICE_DESKTOP = 'desktop';

    /**
     * Substrings that mark a request as automated.
     *
     * Deliberately broad and matched case-insensitively. A false positive
     * costs one unrecorded session; a false negative pollutes the very
     * data this feature exists to produce, and a crawler walking 200 pages
     * would also burn through a session's event cap. When in doubt this
     * errs towards "bot".
     *
     * The bare tokens 'bot', 'crawl' and 'spider' catch the overwhelming
     * majority without naming anyone. The named entries below them are the
     * ones that contain none of those tokens, which is the only reason to
     * list a vendor at all.
     *
     * @var list<string>
     */
    private const BOT_SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'scraper', 'archiver',
        'facebookexternalhit', 'ia_archiver', 'mediapartners',
        'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'curl/', 'wget/', 'python-requests', 'python-urllib', 'go-http-client',
        'java/', 'okhttp', 'axios/', 'guzzlehttp', 'postmanruntime',
        'whatsapp', 'telegrambot', 'discordbot', 'slackbot', 'embedly',
        'preview', 'validator', 'monitoring', 'apachebench', 'siege',
    ];

    /** @var array<string, string> needle => reported name, most specific first */
    private const BROWSERS = [
        'edg/' => 'Edge',
        'edga/' => 'Edge',
        'edgios/' => 'Edge',
        'opr/' => 'Opera',
        'opera' => 'Opera',
        'samsungbrowser' => 'Samsung Internet',
        'ucbrowser' => 'UC Browser',
        'yabrowser' => 'Yandex',
        'firefox' => 'Firefox',
        'fxios' => 'Firefox',
        'crios' => 'Chrome',
        'chrome' => 'Chrome',
        'safari' => 'Safari',
    ];

    /** @var array<string, string> needle => reported name, most specific first */
    private const PLATFORMS = [
        'windows phone' => 'Windows Phone',
        'windows' => 'Windows',
        'android' => 'Android',
        'ipad' => 'iPadOS',
        'iphone' => 'iOS',
        'ipod' => 'iOS',
        'cros' => 'ChromeOS',
        'mac os x' => 'macOS',
        'macintosh' => 'macOS',
        'linux' => 'Linux',
    ];

    public function isBot(?string $userAgent): bool
    {
        // An absent user agent is not a browser. Every real one sends
        // something; scripts frequently send nothing at all.
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        $needle = mb_strtolower($userAgent);

        foreach (self::BOT_SIGNATURES as $signature) {
            if (str_contains($needle, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{device_type: ?string, browser: ?string, platform: ?string}
     */
    public function parse(?string $userAgent): array
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return ['device_type' => null, 'browser' => null, 'platform' => null];
        }

        $needle = mb_strtolower($userAgent);

        return [
            'device_type' => $this->deviceType($needle),
            'browser' => $this->firstMatch($needle, self::BROWSERS),
            'platform' => $this->firstMatch($needle, self::PLATFORMS),
        ];
    }

    /**
     * Tablet before mobile: an Android tablet's UA contains "android" but
     * NOT "mobile", which is the documented way Android distinguishes the
     * two, and an iPad announces "ipad" while matching nothing else useful.
     */
    private function deviceType(string $needle): string
    {
        if (str_contains($needle, 'ipad')
            || str_contains($needle, 'tablet')
            || (str_contains($needle, 'android') && ! str_contains($needle, 'mobile'))) {
            return self::DEVICE_TABLET;
        }

        if (str_contains($needle, 'mobi') || str_contains($needle, 'iphone') || str_contains($needle, 'ipod')) {
            return self::DEVICE_MOBILE;
        }

        return self::DEVICE_DESKTOP;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function firstMatch(string $needle, array $map): ?string
    {
        foreach ($map as $signature => $name) {
            if (str_contains($needle, $signature)) {
                return $name;
            }
        }

        return null;
    }
}
