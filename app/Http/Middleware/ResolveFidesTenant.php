<?php

namespace App\Http\Middleware;

use App\Models\PlatformCooperative;
use App\Models\PlatformAccountLink;
use App\Services\FidesTenantManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveFidesTenant extends SubstituteBindings
{
    public function handle($request, \Closure $next): Response
    {
        if (! config('fides.enabled')) {
            return parent::handle($request, $next);
        }

        $tenantId = $request->session()->get(config('fides.session_tenant_key'));

        if (! $tenantId) {
            app(FidesTenantManager::class)->restoreBaseConnection();

            return parent::handle($request, $next);
        }

        $cooperative = PlatformCooperative::query()
            ->whereKey($tenantId)
            ->where('status', 'active')
            ->first();

        if (! $cooperative) {
            Auth::guard('web')->logout();
            $request->session()->forget(config('fides.session_tenant_key'));

            return redirect()->route('login')->withErrors([
                'identifier' => 'Ruang koperasi tidak tersedia. Silakan masuk kembali.',
            ]);
        }

        app(FidesTenantManager::class)->activate($cooperative);

        $user = Auth::guard('web')->user();
        if ($user && PlatformAccountLink::query()
            ->where('cooperative_id', $cooperative->id)
            ->where('tenant_user_id', $user->id)
            ->where('is_active', false)
            ->exists()) {
            Auth::guard('web')->logout();
            $request->session()->forget(config('fides.session_tenant_key'));
            app(FidesTenantManager::class)->restoreBaseConnection();

            return redirect()->route('login')->withErrors([
                'identifier' => 'Akun koperasi ini telah dinonaktifkan. Hubungi pengelola koperasi.',
            ]);
        }

        return parent::handle($request, $next);
    }
}
