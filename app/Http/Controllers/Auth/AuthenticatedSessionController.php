<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $cooperatives = $request->authenticate();

        $request->session()->regenerate();

        if ($cooperatives) {
            return redirect()->route('login.cooperative.select');
        }

        if (Auth::guard('platform')->check()) {
            return redirect()->route('platform.dashboard');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function selectCooperative(Request $request): View
    {
        $candidateIds = $request->session()->get(config('fides.login_candidates_key'), []);

        abort_unless(config('fides.enabled') && count($candidateIds) > 1, 403);

        return view('auth.select-cooperative', [
            'cooperatives' => \App\Models\PlatformCooperative::query()
                ->whereIn('id', $candidateIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function authenticateSelectedCooperative(Request $request): RedirectResponse
    {
        $candidateIds = $request->session()->get(config('fides.login_candidates_key'), []);
        $validated = $request->validate([
            'cooperative_id' => ['required', 'integer', 'in:'.implode(',', array_map('intval', $candidateIds))],
            'password' => ['required', 'string'],
        ]);

        $identifier = (string) $request->session()->get('fides_login_identifier', '');
        $user = app(\App\Services\FidesAccountDirectory::class)->authenticate(
            $identifier,
            $validated['password'],
            (int) $validated['cooperative_id'],
        );

        if (! $user) {
            app(\App\Services\FidesTenantManager::class)->restoreBaseConnection();
            throw ValidationException::withMessages(['password' => trans('auth.failed')]);
        }

        $request->session()->put(config('fides.session_tenant_key'), (int) $validated['cooperative_id']);
        $request->session()->forget([
            config('fides.login_candidates_key'),
            'fides_login_identifier',
        ]);
        Auth::guard('web')->login($user, $request->boolean('remember'));
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();
        RateLimiter::clear(Str::transliterate(Str::lower($identifier).'|'.$request->ip()));

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        Auth::guard('platform')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
