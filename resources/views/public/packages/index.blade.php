<x-layouts.public :seo-title="__('site.packages.label')" :seo-description="__('site.packages.lead')">
    <x-public.page-hero
        :eyebrow="__('site.packages.eyebrow')"
        :title="__('site.packages.title')"
        :lead="__('site.packages.lead')"
    />

    <section class="section">
        <div class="wrap">
            @if ($packages->isEmpty())
                <p class="text-center text-muted-ink">{{ __('site.packages.empty') }}</p>
            @else
                {{-- The cards. items-start so the popular card's lift does
                     not stretch its neighbours. --}}
                <ul class="grid items-start gap-6 md:grid-cols-2 lg:grid-cols-4">
                    @foreach ($packages as $package)
                        <li @class([
                            'relative flex h-full flex-col rounded-[1.5rem] border p-8',
                            'border-petrol bg-petrol text-white shadow-[0_30px_60px_-24px_rgba(10,37,52,0.5)] lg:-my-2 lg:py-10' => $package->is_popular,
                            'border-line bg-white' => ! $package->is_popular,
                        ])>
                            @if ($package->is_popular)
                                <span class="absolute -top-3 end-6 rounded-full bg-orange-ink px-4 py-1 text-xs font-extrabold text-white">
                                    {{ __('site.packages.popular') }}
                                </span>
                            @endif

                            <h2 @class(['text-xl font-black', 'text-white' => $package->is_popular])>{{ $package->name }}</h2>

                            <p class="mt-3">
                                @if ($package->isPriceOnRequest())
                                    {{-- price = null means "on request",
                                         which is a different fact from a
                                         price of 0.00. No number is
                                         invented to fill the gap. --}}
                                    <span @class([
                                        'text-2xl font-black',
                                        'text-amber' => $package->is_popular,
                                        'text-orange-ink' => ! $package->is_popular,
                                    ])>{{ __('site.packages.price_on_request') }}</span>
                                @else
                                    <span @class([
                                        'text-4xl font-black',
                                        'text-amber' => $package->is_popular,
                                        'text-orange-ink' => ! $package->is_popular,
                                    ]) dir="ltr">{{ number_format((float) $package->price, 0) }}</span>
                                    <span @class([
                                        'text-sm font-medium',
                                        'text-mist-dim' => $package->is_popular,
                                        'text-muted-ink' => ! $package->is_popular,
                                    ])>{{ __('site.packages.currency') }}</span>
                                @endif
                            </p>

                            @if ($package->description)
                                <p @class([
                                    'mt-3 text-sm',
                                    'text-mist' => $package->is_popular,
                                    'text-muted-ink' => ! $package->is_popular,
                                ])>{{ $package->description }}</p>
                            @endif

                            <ul class="mt-5 flex-1 space-y-2.5 text-sm">
                                @foreach ($package->featureList() as $feature)
                                    <li class="flex gap-2.5">
                                        <span @class([
                                            'font-black',
                                            'text-amber' => $package->is_popular,
                                            'text-orange-ink' => ! $package->is_popular,
                                        ]) aria-hidden="true">&check;</span>
                                        <span @class(['text-mist' => $package->is_popular])>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Points at the contact page for now; the
                                 lead capture behind it is the forms step. --}}
                            <a
                                href="{{ lroute('contact') }}"
                                @class([
                                    'btn mt-6 w-full',
                                    'btn-white btn-on-dark' => $package->is_popular,
                                    'btn-ghost-dark' => ! $package->is_popular,
                                ])
                            >
                                {{ __('site.packages.choose') }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- The comparison table. Same data read the other way:
                     the cards answer "what is in this package", this
                     answers "which packages include this".

                     Empty — and therefore skipped — when the packages
                     share no features, because a matrix of one tick per
                     row misrepresents incremental feature lists. See
                     PackageController::comparisonRows(). --}}
                @if ($comparisonRows->isNotEmpty())
                    <div class="mt-16">
                        <h2 class="mb-6 text-center text-2xl font-black">{{ __('site.packages.compare') }}</h2>

                        {{-- overflow-x-auto on the wrapper, never the
                             page: a wide table scrolls inside its own box
                             rather than making the whole document scroll
                             sideways on a phone. --}}
                        <div class="overflow-x-auto rounded-3xl border border-line bg-white">
                            <table class="w-full min-w-[44rem] text-sm">
                                <caption class="sr-only">{{ __('site.packages.compare') }}</caption>
                                <thead class="border-b border-line bg-cream/60">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-start font-extrabold">{{ __('site.packages.feature') }}</th>
                                        @foreach ($packages as $package)
                                            <th scope="col" class="px-4 py-3 text-center font-extrabold">{{ $package->name }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($comparisonRows as $row)
                                        <tr>
                                            <th scope="row" class="px-4 py-3 text-start font-medium text-ink">{{ $row }}</th>
                                            @foreach ($packages as $package)
                                                @php $included = in_array($row, $package->featureList(), true); @endphp
                                                <td class="px-4 py-3 text-center">
                                                    {{-- The glyph is decorative; the
                                                         accessible name carries the
                                                         yes/no, so a screen reader
                                                         does not read a wall of
                                                         "check mark". --}}
                                                    <span class="sr-only">{{ $included ? __('site.packages.included') : __('site.packages.not_included') }}</span>
                                                    <span aria-hidden="true" @class([
                                                        'font-black',
                                                        'text-orange-ink' => $included,
                                                        'text-muted' => ! $included,
                                                    ])>{{ $included ? '✓' : '—' }}</span>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </section>

    <x-public.cta-band />
</x-layouts.public>
