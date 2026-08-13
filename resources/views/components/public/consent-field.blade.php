{{--
    Consent is `accepted` in validation, not merely `boolean` — the box
    has to be genuinely ticked, and the lead records consented_at so the
    agreement is provable per submission rather than assumed site-wide.
--}}
<div>
    <label for="consent_given" class="flex min-h-[44px] cursor-pointer items-start gap-3">
        <input
            id="consent_given"
            name="consent_given"
            type="checkbox"
            value="1"
            @checked(old('consent_given'))
            required
            @error('consent_given') aria-invalid="true" aria-describedby="consent_given-error" @enderror
            class="mt-1 h-5 w-5 shrink-0 rounded border-line text-orange-ink shadow-sm focus:ring-petrol"
        >
        <span class="text-sm text-muted-ink">
            {{ __('site.forms.consent_label') }}
            <span class="mt-1 block text-xs">
                {!! __('site.forms.consent_note', [
                    'policy' => '<a href="'.lroute('pages.privacy-policy').'" class="underline hover:text-orange-ink">'.e(__('site.footer.privacy_link')).'</a>',
                ]) !!}
            </span>
        </span>
    </label>

    @error('consent_given')
        <p id="consent_given-error" class="mt-1 text-sm font-bold text-orange-ink">{{ $message }}</p>
    @enderror
</div>
