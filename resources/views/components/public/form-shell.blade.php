@props(['action', 'id' => null])

{{--
    Every public form goes through here, so the three things each one
    needs cannot be forgotten on a new form: the CSRF token, the hidden
    attribution snapshots, and the honeypot.
--}}
<form
    method="POST"
    action="{{ $action }}"
    @if ($id) id="{{ $id }}" @endif
    data-attribution
    class="space-y-5"
>
    @csrf

    {{-- Filled by resources/js/attribution.js on submit, from the
         first-party cookies. Empty when JavaScript is off — the form
         still works, it just carries no attribution. --}}
    <input type="hidden" name="first_touch_snapshot" value="">
    <input type="hidden" name="latest_touch_snapshot" value="">

    {{--
        The honeypot.

        Hidden from sight AND from assistive tech (aria-hidden +
        tabindex="-1"), so no human is offered it, while a bot filling
        every input in the DOM will. autocomplete="off" stops a browser
        password manager helpfully filling it in for a real visitor,
        which would silently discard their enquiry.

        Positioned off-screen rather than display:none — some bots skip
        fields that are display:none specifically.
    --}}
    <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
        <label for="{{ App\Support\Honeypot::FIELD }}">{{ __('site.forms.honeypot_label') }}</label>
        <input type="text" id="{{ App\Support\Honeypot::FIELD }}" name="{{ App\Support\Honeypot::FIELD }}" tabindex="-1" autocomplete="off" value="">
    </div>

    {{ $slot }}
</form>
