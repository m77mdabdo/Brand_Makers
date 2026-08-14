{{--
    role="status", not "alert": this confirms a completed action rather
    than interrupting, so a screen reader should announce it politely at
    the next pause.

    tabindex="-1" + the anchor target on the form means a visitor who
    submits lands back ON the message rather than at the top of the page
    wondering whether anything happened.
--}}
@if (session('status'))
    <div
        role="status"
        tabindex="-1"
        class="mb-6 flex items-start gap-3 rounded-2xl border border-petrol/20 bg-white px-5 py-4 text-sm font-bold text-petrol"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-orange-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 6 9 17l-5-5" />
        </svg>
        {{ session('status') }}
    </div>
@endif

{{--
    role="alert", unlike the status message above: this one reports that
    what the visitor tried did NOT happen, which is worth interrupting for.
    Used by the booking flow when a slot is taken between rendering the
    page and submitting it.
--}}
@if (session('error'))
    <div
        role="alert"
        tabindex="-1"
        class="mb-6 flex items-start gap-3 rounded-2xl border border-orange-ink/40 bg-white px-5 py-4 text-sm font-bold text-ink"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-orange-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16h.01" />
        </svg>
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    {{-- A summary as well as the per-field errors: on a form several
         screens long the first invalid field can be out of view, so a
         failed submit would otherwise look like nothing happened. --}}
    <div role="alert" class="mb-6 rounded-2xl border border-orange-ink/30 bg-white px-5 py-4 text-sm text-ink">
        <p class="font-bold">{{ __('site.forms.errors_heading') }}</p>
        <ul class="mt-2 list-disc space-y-1 ps-5 marker:text-orange-ink">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
