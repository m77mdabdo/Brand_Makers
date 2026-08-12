{{--
    Session flash messages.

    role="status" (not "alert"): these confirm a completed action rather
    than interrupt, so a screen reader should announce them politely at
    the next pause instead of cutting off whatever is being read.
--}}
@if (session('status'))
    <div
        role="status"
        class="mb-6 flex items-start gap-3 rounded-xl border border-petrol/20 bg-white px-4 py-3 text-sm font-bold text-petrol"
    >
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-orange-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 6 9 17l-5-5" />
        </svg>
        {{ session('status') }}
    </div>
@endif

{{-- A validation summary as well as the per-field errors: on a long form
     like Settings the first invalid field can be several screens away,
     so a failed save would otherwise look like nothing happened. --}}
@if ($errors->any())
    <div
        role="alert"
        class="mb-6 rounded-xl border border-orange-ink/30 bg-white px-4 py-3 text-sm text-ink"
    >
        <ul class="list-disc space-y-1 ps-5 marker:text-orange-ink">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
