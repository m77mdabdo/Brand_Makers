@props(['title' => null, 'lead' => null])

{{-- The closing call to action every inner page ends on. One component so
     the wording and the gradient stay identical across ten pages. --}}
<section class="section pt-0">
    <div class="wrap">
        <div class="rounded-[2rem] bg-[linear-gradient(120deg,#B84A15,#8C370D)] p-10 text-center text-white sm:p-14">
            <h2 class="text-3xl font-black sm:text-4xl">{{ $title ?? __('site.booking.title') }}</h2>
            <p class="mx-auto mt-4 max-w-lg text-lg text-white/95">{{ $lead ?? __('site.booking.lead') }}</p>
            <a href="{{ lroute('contact') }}" class="btn btn-white btn-on-dark mt-7">{{ __('site.booking.cta') }}</a>
        </div>
    </div>
</section>
