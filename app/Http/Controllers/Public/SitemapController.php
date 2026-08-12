<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * Generates sitemap.xml from the routes that actually exist, rather than
 * shipping a static file that goes stale the moment a section is added.
 *
 * Every URL is emitted ONCE with xhtml:link alternates for both locales
 * (plus x-default → Arabic), matching the hreflang tags the pages
 * themselves render. Listing / and /en as two unrelated entries instead
 * would tell a crawler they are competing documents rather than
 * translations of one.
 *
 * Registered outside the dual-locale route closure in web.php: a sitemap
 * is one document describing the whole site, not a page that needs its
 * own translation.
 */
class SitemapController extends Controller
{
    /**
     * Parameter-free public routes, with the priority/changefreq hint
     * appropriate to each.
     *
     * Only routes that are ACTUALLY REGISTERED are emitted (see __invoke),
     * so this list can name the full eventual sitemap while the site is
     * still being built out one section at a time — an entry for a page
     * that does not exist yet would be a soft 404 in a crawler's queue.
     *
     * @var array<string, array{priority: string, changefreq: string}>
     */
    private const STATIC_ROUTES = [
        'home' => ['priority' => '1.0', 'changefreq' => 'weekly'],
        'services.index' => ['priority' => '0.9', 'changefreq' => 'weekly'],
        'packages.index' => ['priority' => '0.9', 'changefreq' => 'weekly'],
        'estimator' => ['priority' => '0.8', 'changefreq' => 'monthly'],
        'booking' => ['priority' => '0.8', 'changefreq' => 'monthly'],
        'contact' => ['priority' => '0.7', 'changefreq' => 'monthly'],
        'pages.about' => ['priority' => '0.7', 'changefreq' => 'monthly'],
        'pages.our-process' => ['priority' => '0.7', 'changefreq' => 'monthly'],
        'articles.index' => ['priority' => '0.6', 'changefreq' => 'weekly'],
        'countries.index' => ['priority' => '0.5', 'changefreq' => 'monthly'],
        'faqs.index' => ['priority' => '0.5', 'changefreq' => 'monthly'],
        'pages.privacy-policy' => ['priority' => '0.3', 'changefreq' => 'yearly'],
        'pages.terms-and-conditions' => ['priority' => '0.3', 'changefreq' => 'yearly'],
    ];

    public function __invoke(): Response
    {
        $entries = [];

        foreach (self::STATIC_ROUTES as $name => $meta) {
            // Both variants must exist. A route registered in only one
            // locale has no alternate to advertise.
            if (! Route::has($name) || ! Route::has($name.'.en')) {
                continue;
            }

            $entries[] = [
                'ar' => route($name),
                'en' => route($name.'.en'),
                'lastmod' => null,
                'priority' => $meta['priority'],
                'changefreq' => $meta['changefreq'],
            ];
        }

        return response($this->render($entries), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function render(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($entries as $entry) {
            // The Arabic (no-prefix) URL is the canonical <loc>, matching
            // the x-default the pages themselves declare.
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($entry['ar']).'</loc>'."\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="ar" href="'.e($entry['ar']).'"/>'."\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="en" href="'.e($entry['en']).'"/>'."\n";
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="'.e($entry['ar']).'"/>'."\n";

            if ($entry['lastmod']) {
                $xml .= '    <lastmod>'.e($entry['lastmod']).'</lastmod>'."\n";
            }

            $xml .= '    <changefreq>'.e($entry['changefreq']).'</changefreq>'."\n";
            $xml .= '    <priority>'.e($entry['priority']).'</priority>'."\n";
            $xml .= "  </url>\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
