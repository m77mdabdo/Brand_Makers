<x-layouts.public :seo-title="__('site.contact.label')" :seo-description="__('site.contact.lead')">
    <x-public.page-hero
        :eyebrow="__('site.contact.eyebrow')"
        :title="__('site.contact.title')"
        :lead="__('site.contact.lead')"
    />

    <section class="section">
        <div class="wrap max-w-3xl">
            @php $hasDetails = $phone || $email || $whatsapp || $address; @endphp

            @if ($hasDetails)
                <ul class="grid gap-4 sm:grid-cols-2">
                    {{-- Every row is conditional. The contact settings ship
                         EMPTY rather than with placeholder details, so an
                         unconfigured install renders nothing here instead
                         of a fake phone number. --}}
                    @if ($whatsapp)
                        <li class="card">
                            <h2 class="font-extrabold">{{ __('site.contact.whatsapp') }}</h2>
                            <a
                                href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsapp) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                dir="ltr"
                                class="mt-1 inline-flex min-h-[44px] items-center text-orange-ink underline"
                            >{{ $whatsapp }}</a>
                        </li>
                    @endif

                    @if ($phone)
                        <li class="card">
                            <h2 class="font-extrabold">{{ __('site.contact.phone') }}</h2>
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" dir="ltr" class="mt-1 inline-flex min-h-[44px] items-center text-orange-ink underline">{{ $phone }}</a>
                        </li>
                    @endif

                    @if ($email)
                        <li class="card">
                            <h2 class="font-extrabold">{{ __('site.contact.email') }}</h2>
                            <a href="mailto:{{ $email }}" dir="ltr" class="mt-1 inline-flex min-h-[44px] items-center text-orange-ink underline">{{ $email }}</a>
                        </li>
                    @endif

                    @if ($address)
                        <li class="card">
                            <h2 class="font-extrabold">{{ __('site.contact.address') }}</h2>
                            <p class="mt-1 text-sm text-muted-ink">{{ $address }}</p>
                        </li>
                    @endif
                </ul>
            @else
                <p class="text-center text-muted-ink">{{ __('site.contact.no_details') }}</p>
            @endif

            @if ($socialLinks)
                <div class="mt-8 text-center">
                    <h2 class="font-extrabold">{{ __('site.contact.social') }}</h2>
                    <ul class="mt-3 flex flex-wrap justify-center gap-2">
                        @foreach ($socialLinks as $platform => $url)
                            <li>
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex min-h-[44px] items-center rounded-full border border-line px-4 text-sm font-bold text-petrol transition hover:border-petrol hover:bg-white"
                                >{{ ucfirst($platform) }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div id="contact-form" class="mt-12 scroll-mt-24">
                <h2 class="text-2xl font-black">{{ __('site.forms.heading') }}</h2>
                <p class="mt-1 text-sm text-muted-ink">{{ __('site.forms.lead') }}</p>

                <div class="mt-6">
                    <x-public.flash />

                    <x-public.form-shell :action="lroute('contact.store')">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-public.form-field name="full_name" :label="__('site.forms.name')" :required="true" />
                            {{-- dir="ltr" whatever the interface language:
                                 an email address and a phone number are
                                 never Arabic text, and an RTL field renders
                                 them with the wrong end first. --}}
                            <x-public.form-field name="email" type="email" dir="ltr" :label="__('site.forms.email')" :required="true" />
                        </div>

                        <x-public.form-field name="phone" type="tel" dir="ltr" :label="__('site.forms.phone')" />

                        <x-public.form-field name="message" type="textarea" :label="__('site.forms.message')" :required="true" />

                        <x-public.consent-field />

                        <button type="submit" class="btn btn-primary w-full sm:w-auto">
                            {{ __('site.forms.submit') }}
                        </button>
                    </x-public.form-shell>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
