<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}" class="dk-password-form">
    @csrf
    @method('patch')
    <input type="hidden" name="_profile_form" value="profile">
    <button type="button" class="dk-modal-close" aria-label="Tutup" data-close-dialog>×</button>
    <p class="dk-eyebrow">AKUN SAYA</p>
    <h3>Informasi profil</h3>
    <p class="dk-profile-form-description">Perbarui nama, email, dan nomor HP akun Anda.</p>

    <div>
        <x-input-label for="name" value="Nama" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div>
                <p class="mt-2 text-sm">
                    Email Anda belum diverifikasi.

                    <button form="send-verification" class="text-sm underline">
                        Kirim ulang email verifikasi.
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium">
                        Email verifikasi baru telah dikirim.
                    </p>
                @endif
            </div>
        @endif
    </div>

    <div>
        <x-input-label for="phone" value="Nomor HP" />
        <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" :required="$user->isBorrower()" autocomplete="tel" />
        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
    </div>

    <button type="submit" class="dk-button-primary">Simpan perubahan</button>
</form>
