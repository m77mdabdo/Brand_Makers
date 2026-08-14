@php
    use App\Services\Cms\SettingsService;
@endphp

<x-layouts.app :title="__('dashboard.settings.heading')">
    <x-slot:header>
        <h1 class="text-2xl font-black tracking-tight text-ink">{{ __('dashboard.settings.heading') }}</h1>
        <p class="mt-1 max-w-2xl text-sm text-muted-ink">{{ __('dashboard.settings.intro') }}</p>
    </x-slot:header>

    {{-- enctype because the logo is a file upload; @method('PUT') because
         this replaces the whole settings set rather than patching one. --}}
    <form method="POST" action="{{ route('dashboard.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        {{--
            Rendered by looping the DEFINITION, not by listing fields here.
            A key added to SettingsService appears in this form, gets a
            validation rule and gets persisted, with no other edit — and
            nothing outside the definition can be rendered, submitted or
            written.
        --}}
        @foreach ($definition as $group => $fields)
            <section class="rounded-2xl border border-line bg-white p-6">
                <h2 class="text-lg font-extrabold text-ink">{{ __('dashboard.settings.groups.'.$group) }}</h2>

                @if (Lang::has('dashboard.settings.group_help.'.$group))
                    <p class="mt-1 text-sm text-muted-ink">{{ __('dashboard.settings.group_help.'.$group) }}</p>
                @endif

                <div class="mt-5 space-y-5">
                    @foreach ($fields as $key => $type)
                        @php
                            $label = __('dashboard.settings.fields.'.$key);
                            $name = "settings[{$key}]";
                            $translated = in_array($type, [SettingsService::TYPE_TRANSLATED, SettingsService::TYPE_TRANSLATED_AREA], true);
                            $isArea = in_array($type, [SettingsService::TYPE_TEXTAREA, SettingsService::TYPE_TRANSLATED_AREA], true);
                        @endphp

                        <div>
                            @if ($type === SettingsService::TYPE_IMAGE)
                                <x-input-label :for="$key" :value="$label" />

                                @php $stored = $settings->string($key); @endphp
                                @if ($stored)
                                    <div class="mt-2 flex items-center gap-3">
                                        <img src="{{ Storage::url($stored) }}" alt="" class="h-12 w-auto rounded-lg border border-line bg-cream p-1">
                                        <span class="text-xs text-muted-ink">{{ __('dashboard.common.current') }}</span>
                                    </div>
                                @endif

                                {{-- No SVG in `accept`, matching the
                                     validation rule — an uploaded SVG is
                                     an executable document served from
                                     this origin. --}}
                                <input
                                    id="{{ $key }}"
                                    name="site_logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="mt-2 block w-full min-h-[44px] rounded-lg border border-line bg-white text-sm text-ink file:me-3 file:min-h-[44px] file:cursor-pointer file:border-0 file:bg-cream-2 file:px-4 file:py-2 file:text-sm file:font-bold file:text-petrol"
                                >
                                <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.settings.logo_help') }}</p>
                                <x-input-error :messages="$errors->get('site_logo')" class="mt-2" />

                            @elseif ($translated)
                                @php $values = $settings->raw($key); @endphp

                                <fieldset>
                                    <legend class="block text-sm font-bold text-ink">{{ $label }}</legend>

                                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                        @foreach (['ar' => __('dashboard.common.arabic'), 'en' => __('dashboard.common.english')] as $locale => $localeLabel)
                                            <div>
                                                <label for="{{ $key }}_{{ $locale }}" class="mb-1 block text-xs font-bold text-muted-ink">
                                                    {{ $localeLabel }}
                                                </label>

                                                @if ($isArea)
                                                    <textarea
                                                        id="{{ $key }}_{{ $locale }}"
                                                        name="{{ $name }}[{{ $locale }}]"
                                                        rows="3"
                                                        dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"
                                                        class="block w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                                    >{{ old("settings.{$key}.{$locale}", $values[$locale] ?? '') }}</textarea>
                                                @else
                                                    <input
                                                        id="{{ $key }}_{{ $locale }}"
                                                        name="{{ $name }}[{{ $locale }}]"
                                                        type="text"
                                                        dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"
                                                        value="{{ old("settings.{$key}.{$locale}", $values[$locale] ?? '') }}"
                                                        class="block w-full min-h-[44px] rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                                    >
                                                @endif

                                                <x-input-error :messages="$errors->get(\"settings.{$key}.{$locale}\")" class="mt-1" />
                                            </div>
                                        @endforeach
                                    </div>
                                </fieldset>

                            @elseif ($type === SettingsService::TYPE_BOOLEAN)
                                {{-- The hidden "0" is what makes an
                                     UNCHECKED box submit at all. Without
                                     it the key is simply absent, and
                                     while SettingsService already reads a
                                     missing boolean as false, relying on
                                     that would mean the form and the
                                     service disagreed about what was
                                     sent. --}}
                                <label for="{{ $key }}" class="flex items-start gap-3">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input
                                        id="{{ $key }}"
                                        name="{{ $name }}"
                                        type="checkbox"
                                        value="1"
                                        @checked((bool) old("settings.{$key}", $settings->boolean($key)))
                                        class="mt-0.5 h-5 w-5 rounded border-line text-petrol focus:ring-petrol"
                                    >
                                    <span class="text-sm font-bold text-ink">{{ $label }}</span>
                                </label>

                                @if (Lang::has('dashboard.settings.field_help.'.$key))
                                    <p class="ms-8 mt-1 text-xs text-muted-ink">{{ __('dashboard.settings.field_help.'.$key) }}</p>
                                @endif

                                <x-input-error :messages="$errors->get(\"settings.{$key}\")" class="mt-2" />

                            @else
                                <x-input-label :for="$key" :value="$label" />

                                {{-- dir="ltr" on phone/email/url regardless
                                     of the interface language: these are
                                     never Arabic text, and an RTL input
                                     renders "+966 5…" with the sign at the
                                     wrong end. --}}
                                <input
                                    id="{{ $key }}"
                                    name="{{ $name }}"
                                    type="{{ match ($type) {
                                        SettingsService::TYPE_EMAIL => 'email',
                                        SettingsService::TYPE_PHONE => 'tel',
                                        SettingsService::TYPE_URL => 'url',
                                        SettingsService::TYPE_INTEGER => 'number',
                                        default => 'text',
                                    } }}"
                                    @if (in_array($type, [SettingsService::TYPE_EMAIL, SettingsService::TYPE_PHONE, SettingsService::TYPE_URL], true))
                                        dir="ltr"
                                    @endif
                                    @if ($type === SettingsService::TYPE_INTEGER)
                                        {{-- Mirrors the rule in
                                             UpdateSettingsRequest, so the
                                             browser refuses the same
                                             values the server would. --}}
                                        min="{{ $key === 'booking_minimum_notice_hours' ? 0 : 1 }}" max="3650" step="1" dir="ltr"
                                    @endif
                                    {{-- string() returns null for an int,
                                         because it folds every non-string
                                         to the default — so an integer
                                         setting read through it would
                                         render as an empty box and save
                                         as null on the next submit. --}}
                                    value="{{ old("settings.{$key}", $type === SettingsService::TYPE_INTEGER ? ($settings->integer($key) ?: '') : $settings->string($key)) }}"
                                    class="mt-1 block w-full min-h-[44px] rounded-lg border-line bg-white text-sm text-ink shadow-sm focus:border-petrol focus:ring-petrol"
                                >

                                @if (Lang::has('dashboard.settings.field_help.'.$key))
                                    <p class="mt-1 text-xs text-muted-ink">{{ __('dashboard.settings.field_help.'.$key) }}</p>
                                @endif

                                <x-input-error :messages="$errors->get(\"settings.{$key}\")" class="mt-2" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                {{ __('dashboard.common.save') }}
            </button>
        </div>
    </form>
</x-layouts.app>
