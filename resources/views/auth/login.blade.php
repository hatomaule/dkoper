<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div>
            <x-input-label for="identifier" :value="__('ID / Username / Nomor HP')" />
            <x-text-input id="identifier" class="block mt-2 w-full" type="text" name="identifier" :value="old('identifier')" required autofocus autocomplete="username" placeholder="Masukkan ID, username, atau nomor HP" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="Masukkan password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-5">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a class="float-right text-sm font-semibold text-emerald-700 hover:text-emerald-900" href="{{ route('password.request') }}">
                    Lupa password?
                </a>
            @endif
        </div>

        <div class="dk-login-actions">
            <x-primary-button class="dk-login-button justify-center">
                Login
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
