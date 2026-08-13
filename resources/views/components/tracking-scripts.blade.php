@php
    /**
     * The marketing tags.
     *
     * Every snippet below is conditional on its own row being both
     * is_active AND non-empty — a platform with no ID entered, or one
     * entered but toggled off, renders nothing at all. IDs are never
     * hardcoded here; they only ever come from the tracking_settings
     * table, edited on the dashboard's Tracking Settings screen, and each
     * one is format-validated on write (see TrackingSetting::FORMATS)
     * before it can reach an inline script.
     *
     * CONSENT. Nothing here tracks anybody until the visitor accepts the
     * cookie banner. Two different mechanisms, because the platforms
     * differ:
     *
     *   GOOGLE (GTM + GA4 + Ads) uses Consent Mode v2. gtag.js still
     *   loads, but the FIRST thing on the page sets every consent signal
     *   to 'denied', so it sets no cookies and sends no identifiers — only
     *   cookieless pings, which is what feeds Google's conversion
     *   modelling. On consent it is updated to 'granted'. This is the
     *   mechanism Google designed for exactly this case, and the reason to
     *   prefer it over withholding the tag: a site that does not load it
     *   at all loses the modelled data too.
     *
     *   META and TIKTOK have no equivalent. Their consent APIs still
     *   require loading the vendor script first, and that request itself
     *   carries the visitor's IP to a third party. So those two are not
     *   loaded at all until consent — the snippets sit inside
     *   cmPixels.load(), and nothing calls it until the visitor agrees.
     *
     * Consent arrives by two routes and both are handled: this component
     * calls the loader server-side when the request ALREADY carries the
     * consent cookie (a returning visitor, tracked from the first paint),
     * and resources/js/visitor-session.js calls it the moment the banner
     * is accepted (a new visitor, tracked without needing a reload).
     *
     * marketingAllowed(), NOT allows(): the pixels are governed by consent
     * and the DNT/GPC override alone. The visitor_sessions_enabled switch
     * belongs to journey recording and must not silently disable
     * advertising — see VisitorTrackingGate::marketingAllowed().
     */
    use App\Services\Analytics\VisitorTrackingGate;

    $tracking = \App\Models\TrackingSetting::query()
        ->where('is_active', true)
        ->whereNotNull('value')
        ->where('value', '!=', '')
        ->get()
        ->keyBy('key');

    $metaPixel = $tracking->get('meta_pixel_id');
    $gtm = $tracking->get('gtm_container_id');
    $gtagTargets = collect([$tracking->get('ga4_measurement_id'), $tracking->get('google_ads_conversion_id')])->filter();
    $tiktok = $tracking->get('tiktok_pixel_id');

    $hasGoogle = $gtm || $gtagTargets->isNotEmpty();
    $hasDeferred = $metaPixel || $tiktok;

    // Read once, server-side. app(...) rather than a view composer because
    // this component is the only thing that needs it.
    $marketingConsent = app(VisitorTrackingGate::class)->marketingAllowed(request());
@endphp

@if ($hasGoogle)
    {{--
        Consent Mode v2 defaults. This block MUST come before gtag.js and
        before the GTM container, or the tag will already have run once in
        its unrestricted default state — the ordering IS the mechanism, not
        a style preference.
    --}}
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}

        gtag('consent', 'default', {
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'analytics_storage': 'denied',
            'personalization_storage': 'denied',
            /* Not tracking signals: these cover things like remembering a
               language choice and anti-abuse, which the site needs to
               work at all. */
            'functionality_storage': 'granted',
            'security_storage': 'granted',
            /* Holds tags for 500ms so a consent update landing moments
               later is applied to the first event rather than after it. */
            'wait_for_update': 500
        });

        window.cmConsent = window.cmConsent || {};
        window.cmConsent.grantGoogle = function () {
            gtag('consent', 'update', {
                'ad_storage': 'granted',
                'ad_user_data': 'granted',
                'ad_personalization': 'granted',
                'analytics_storage': 'granted',
                'personalization_storage': 'granted'
            });
        };

        @if ($marketingConsent)
            /* Returning visitor who already agreed: granted before the tag
               below loads, so nothing is lost to the denied window. */
            window.cmConsent.grantGoogle();
        @endif
    </script>
@endif

@if ($gtm)
    {{-- Google Tag Manager. Reads the consent state from the dataLayer
         established above. --}}
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtm->value }}');
    </script>
@endif

@if ($gtagTargets->isNotEmpty())
    {{-- GA4 / Google Ads — one shared gtag.js loader, one gtag('config')
         per active ID, so both can be active at once without loading the
         loader twice. dataLayer and gtag() are already defined by the
         consent block above. --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gtagTargets->first()->value }}"></script>
    <script>
        gtag('js', new Date());
        @foreach ($gtagTargets as $target)
            gtag('config', '{{ $target->value }}');
        @endforeach
    </script>
@endif

@if ($hasDeferred)
    {{--
        Meta and TikTok, DEFINED BUT NOT EXECUTED.

        Nothing in here runs — and no request reaches facebook.net or
        tiktok.com — until cmPixels.load() is called. The IDs being present
        in the page source is harmless: they are public identifiers that
        appear in any page using them, which the Tracking Settings screen
        says in as many words.
    --}}
    <script>
        window.cmPixels = window.cmPixels || {};
        window.cmPixels.loaded = false;

        window.cmPixels.load = function () {
            /* Guarded because both the server block below and the consent
               banner can call this, and a double-loaded pixel double-counts
               every PageView. */
            if (window.cmPixels.loaded) {
                return;
            }
            window.cmPixels.loaded = true;

            @if ($metaPixel)
                /* Meta (Facebook) Pixel — official base snippet. */
                !function(f,b,e,v,n,t,s)
                {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '{{ $metaPixel->value }}');
                fbq('track', 'PageView');
            @endif

            @if ($tiktok)
                /* TikTok Pixel — official base snippet. */
                !function (w, d, t) {
                    w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<e.length;n++)ttq.setAndDefer(e,e[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
                    ttq.load('{{ $tiktok->value }}');
                    ttq.page();
                }(window, document, 'ttq');
            @endif
        };

        @if ($marketingConsent)
            /* Returning visitor who already agreed. */
            window.cmPixels.load();
        @endif
    </script>
@endif

@if ($metaPixel && $marketingConsent)
    {{--
        The Meta no-JS fallback. Gated on the SERVER because it is an
        <img>: it fires on parse with no JavaScript involved, so the
        client-side loader above cannot hold it back.

        A visitor with JavaScript disabled can never have accepted the
        banner (it is an Alpine component), so in practice this renders
        only for someone who consented earlier and has since turned
        JavaScript off.
    --}}
    <noscript>
        <img height="1" width="1" style="display:none" alt=""
            src="https://www.facebook.com/tr?id={{ $metaPixel->value }}&ev=PageView&noscript=1">
    </noscript>
@endif
