<x-app-layout>
    @if (auth()->user()->must_change_password)
        <x-slot name="header">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Amankan akun Anda</h2>
                <p class="mt-1 text-sm text-slate-500">Buat kata sandi baru sebelum melanjutkan ke {{ config('app.name') }}.</p>
            </div>
        </x-slot>

        <div class="min-h-[calc(100vh-150px)] px-4 py-8">
            <section class="mx-auto max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6">
                    <p class="dk-eyebrow">LANGKAH PERTAMA</p>
                    <h3 class="text-lg font-bold text-slate-900">Ganti kata sandi awal</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Untuk melindungi akun, kata sandi awal hanya digunakan saat masuk pertama kali. Pilih kata sandi pribadi yang kuat dan simpan dengan aman.
                    </p>
                </div>

                <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="password" value="Kata sandi baru" />
                        <x-text-input id="password" name="password" type="password" class="mt-2 block w-full" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Ulangi kata sandi baru" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-2 block w-full" required autocomplete="new-password" />
                    </div>

                    <button type="submit" class="dk-button-primary w-full rounded-md px-4 py-3 text-sm font-bold text-white">
                        Simpan dan lanjutkan
                    </button>
                </form>
            </section>
        </div>
    @else
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
    @endif
</x-app-layout>
