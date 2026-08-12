<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-orange-ink border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-ink active:bg-orange-ink-hover focus:outline-none focus:ring-2 focus:ring-orange-ink focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
