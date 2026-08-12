<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white border border-line rounded-md font-semibold text-xs text-ink uppercase tracking-widest shadow-sm hover:bg-cream focus:outline-none focus:ring-2 focus:ring-petrol focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
