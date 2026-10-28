<x-guest-layout>
    <p class="dk-eyebrow">PILIH RUANG KOPERASI</p>
    <h1>Akun ini terdaftar di beberapa koperasi.</h1>
    <p class="dk-guest-intro">Pilih ruang yang ingin dibuka, lalu masukkan kembali password.</p>

    <form method="POST" action="{{ route('login.cooperative.authenticate') }}" class="space-y-4">
        @csrf
        <label class="block">
            <span class="text-sm font-medium">Koperasi</span>
            <select name="cooperative_id" required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm">
                @foreach ($cooperatives as $cooperative)
                    <option value="{{ $cooperative->id }}">{{ $cooperative->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-sm font-medium">Password</span>
            <input type="password" name="password" required autocomplete="current-password" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm">
        </label>
        @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <button type="submit" class="dk-login-button">Masuk ke koperasi</button>
    </form>
</x-guest-layout>
