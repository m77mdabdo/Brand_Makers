<x-layouts.guest :title="__('dashboard.auth.login_title')">
    <div class="text-center">
        <h1 class="text-2xl font-black text-ink">{{ __('dashboard.auth.login_title') }}</h1>
        <p class="mt-1 text-sm text-muted-ink">{{ __('dashboard.auth.login_intro') }}</p>
    </div>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('dashboard.auth.email')" />
            {{-- dir="ltr" whatever the interface language: an email
                 address is never Arabic text, and an RTL field renders it
                 with the domain at the wrong end. --}}
            <x-text-input
                id="email"
                class="mt-1 block w-full min-h-[44px]"
                type="email"
                name="email"
                dir="ltr"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('dashboard.auth.password')" />
            <x-text-input
                id="password"
                class="mt-1 block w-full min-h-[44px]"
                type="password"
                name="password"
                dir="ltr"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex min-h-[44px] cursor-pointer items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-line text-orange-ink shadow-sm focus:ring-petrol">
                <span class="text-sm text-muted-ink">{{ __('dashboard.auth.remember_me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    class="inline-flex min-h-[44px] items-center rounded text-sm font-bold text-orange-ink underline transition hover:text-orange-ink-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-petrol"
                >
                    {{ __('dashboard.auth.forgot_password') }}
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-full">
            {{ __('dashboard.auth.login') }}
        </button>
    </form>
</x-layouts.guest>
