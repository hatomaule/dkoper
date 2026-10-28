<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): ?array
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim($this->string('identifier')->toString());
        $password = $this->string('password')->toString();

        if (! config('fides.enabled')) {
            $user = \App\Models\User::query()
                ->where('username', $identifier)
                ->orWhere('phone', $identifier)
                ->orWhereHas('borrower', fn ($query) => $query->where('borrower_code', $identifier))
                ->first();

            if (! $user || ! Auth::attempt(['username' => $user->username, 'password' => $password], $this->boolean('remember'))) {
                $this->failedAuthentication();
            }

            $user->forceFill(['last_login_at' => now()])->save();
            RateLimiter::clear($this->throttleKey());

            return null;
        }

        $admin = \App\Models\PlatformAdmin::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($admin && Hash::check($password, $admin->password)) {
            Auth::guard('platform')->login($admin, $this->boolean('remember'));
            $this->session()->forget(config('fides.session_tenant_key'));
            RateLimiter::clear($this->throttleKey());

            return null;
        }

        $directory = app(\App\Services\FidesAccountDirectory::class);
        $candidates = $directory->candidates($identifier);
        $valid = [];

        foreach ($candidates as $candidate) {
            $user = $directory->authenticate($identifier, $password, $candidate->cooperative_id);

            if ($user) {
                $valid[] = [$candidate->cooperative, $user];
            }
        }

        if (count($valid) === 0) {
            app(\App\Services\FidesTenantManager::class)->restoreBaseConnection();
            $this->failedAuthentication();
        }

        if (count($valid) > 1) {
            $ids = collect($valid)->map(fn ($item) => $item[0]->id)->all();
            app(\App\Services\FidesTenantManager::class)->restoreBaseConnection();
            $this->session()->put(config('fides.login_candidates_key'), $ids);
            $this->session()->put('fides_login_identifier', $identifier);
            RateLimiter::clear($this->throttleKey());

            return collect($valid)->map(fn ($item) => $item[0])->all();
        }

        [$cooperative, $user] = $valid[0];
        app(\App\Services\FidesTenantManager::class)->activate($cooperative);
        $this->session()->put(config('fides.session_tenant_key'), $cooperative->id);
        Auth::guard('web')->login($user, $this->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();
        RateLimiter::clear($this->throttleKey());

        return null;
    }

    private function failedAuthentication(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('identifier')).'|'.$this->ip());
    }
}
