<?php

namespace App\Http\Controllers;

use App\Models\PlatformCooperative;
use App\Models\PlatformAccountLink;
use App\Models\User;
use App\Services\FidesCooperativeProvisioner;
use App\Services\FidesTenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class PlatformController extends Controller
{
    public function index(): View
    {
        $cooperatives = $this->cooperativeOverviews(
            PlatformCooperative::query()->orderByDesc('created_at')->orderByDesc('id')->get(),
        );

        return view('platform.dashboard', [
            'cooperatives' => $cooperatives,
            'totals' => [
                'cooperatives' => $cooperatives->count(),
                'active' => $cooperatives->where('cooperative.status', 'active')->count(),
                'users' => $cooperatives->sum('stats.users'),
                'borrowers' => $cooperatives->sum('stats.borrowers'),
            ],
        ]);
    }

    public function cooperatives(): View
    {
        $cooperatives = $this->cooperativeOverviews(
            PlatformCooperative::query()->orderByDesc('created_at')->orderByDesc('id')->get(),
        );

        return view('platform.cooperatives', compact('cooperatives'));
    }

    public function accounts(Request $request): View
    {
        $overviews = $this->cooperativeOverviews(
            PlatformCooperative::query()->orderBy('name')->get(),
        );
        $cooperatives = $overviews->pluck('cooperative')->values();
        $selectedCooperativeId = (int) $request->query('cooperative', $cooperatives->first()?->id ?? 0);

        return view('platform.accounts', [
            'cooperatives' => $cooperatives,
            'selectedCooperativeId' => $selectedCooperativeId,
            'overview' => $overviews->first(fn (array $overview): bool => (int) $overview['cooperative']->id === $selectedCooperativeId),
        ]);
    }

    public function show(PlatformCooperative $cooperative): View
    {
        abort_unless($cooperative->status === 'active', 404);

        $overview = $this->cooperativeOverviews(collect([$cooperative]))->first();

        return view('platform.cooperative', compact('cooperative', 'overview'));
    }

    public function store(Request $request, FidesCooperativeProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('platform.platform_cooperatives', 'slug'),
            ],
            'owner_username' => ['required', 'string', 'max:50', 'alpha_dash:ascii'],
            'owner_password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $result = $provisioner->create(
                $validated['name'],
                Str::slug($validated['slug']),
                $validated['owner_username'],
                $validated['owner_password'],
            );
        } catch (RuntimeException $exception) {
            return back()->withInput($request->except(['owner_password', 'owner_password_confirmation']))
                ->withErrors(['cooperative' => $exception->getMessage()]);
        }

        $status = "Ruang {$result['cooperative']->name} berhasil dibuat.";
        if (($result['cooperative']->connection_config['driver'] ?? null) === 'sqlite') {
            $status .= ' Database koperasi menggunakan file SQLite terpisah.';
        }

        return redirect()->route('platform.dashboard')
            ->with('status', $status)
            ->with('temporary_admin_credentials', [
                'username' => $result['admin_username'],
                'password' => $result['admin_password'],
            ]);
    }

    public function storeStaffAccount(Request $request, int $cooperativeId): RedirectResponse
    {
            $cooperative = PlatformCooperative::query()
                ->whereKey($cooperativeId)
                ->where('status', 'active')
                ->firstOrFail();
            $tenantManager = app(FidesTenantManager::class);
            $tenantManager->activate($cooperative);

            try {
                $validated = $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'username' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('users', 'username')],
                    'role' => ['required', Rule::in(['manager', 'kasir'])],
                    'password' => ['required', 'confirmed', Password::defaults()],
                ]);

                $account = DB::transaction(fn (): User => User::query()->create([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                    'role' => $validated['role'],
                    'must_change_password' => true,
                ]));
            } finally {
                $tenantManager->restoreBaseConnection();
            }

            $roleLabel = $account->role === 'manager' ? 'Owner' : 'Admin';

            return back()->with('status', "Akun {$roleLabel} {$account->username} berhasil dibuat. Pengguna wajib mengganti password saat masuk.");
    }

    public function updateAccount(Request $request, int $cooperativeId, int $userId): RedirectResponse
    {
        $cooperative = PlatformCooperative::query()
            ->whereKey($cooperativeId)
            ->where('status', 'active')
            ->firstOrFail();
        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($cooperative);

        try {
            $user = DB::table('users')
                ->where('id', $userId)
                ->whereIn('role', ['manager', 'kasir'])
                ->first();

            abort_if(! $user, 404);

            $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'username' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:50',
                    'alpha_dash:ascii',
                    Rule::unique('users', 'username')->ignore($userId),
                ],
                'password' => ['nullable', 'confirmed', Password::defaults()],
            ]);

            $account = User::query()->findOrFail($userId);
            if (array_key_exists('name', $validated)) {
                $account->name = $validated['name'];
            }
            if (array_key_exists('username', $validated)) {
                $account->username = filled($validated['username']) ? $validated['username'] : null;
            }
            if ($validated['password'] ?? null) {
                $account->password = $validated['password'];
                $account->must_change_password = true;
            }

            DB::transaction(function () use ($account): void {
                $account->save();
            });
        } finally {
            $tenantManager->restoreBaseConnection();
        }

        return back()->with('status', 'Akun '.($account->username ?: $account->name).' berhasil diperbarui.');
    }

    public function resetAccountPassword(Request $request, int $cooperativeId, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
            'confirm_account_action' => ['accepted'],
        ]);
        $cooperative = PlatformCooperative::query()
            ->whereKey($cooperativeId)
            ->where('status', 'active')
            ->firstOrFail();
        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($cooperative);

        try {
            $account = User::query()->whereIn('role', ['manager', 'kasir'])->findOrFail($userId);
            $account->password = Hash::make($validated['password']);
            $account->must_change_password = true;
            $account->save();
        } finally {
            $tenantManager->restoreBaseConnection();
        }

        return back()->with('status', "Password akun {$account->username} berhasil direset. Pengguna wajib menggantinya saat masuk.");
    }

    public function updateAccountStatus(Request $request, int $cooperativeId, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'confirm_account_action' => ['accepted'],
        ]);
        $cooperative = PlatformCooperative::query()
            ->whereKey($cooperativeId)
            ->where('status', 'active')
            ->firstOrFail();
        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($cooperative);

        try {
            $account = User::query()->whereIn('role', ['manager', 'kasir'])->findOrFail($userId);
            $isActive = (bool) $validated['is_active'];
            $this->guardLastOwner($account, $isActive);

            PlatformAccountLink::query()->updateOrCreate(
                ['cooperative_id' => $cooperative->id, 'tenant_user_id' => $account->id],
                [
                    'username' => $account->username,
                    'phone' => $account->phone,
                    'borrower_code' => null,
                    'is_active' => $isActive,
                ],
            );
        } finally {
            $tenantManager->restoreBaseConnection();
        }

        $status = $isActive ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('status', "Akun {$account->username} berhasil {$status}.");
    }

    public function destroyAccount(Request $request, int $cooperativeId, int $userId): RedirectResponse
    {
        $request->validate(['confirm_account_action' => ['accepted']]);
        $cooperative = PlatformCooperative::query()
            ->whereKey($cooperativeId)
            ->where('status', 'active')
            ->firstOrFail();
        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($cooperative);

        try {
            $account = User::query()->whereIn('role', ['manager', 'kasir'])->findOrFail($userId);
            $this->guardLastOwner($account, false);

            if ($this->accountHasHistory($account)) {
                throw ValidationException::withMessages([
                    'account' => 'Akun memiliki riwayat operasional. Nonaktifkan akun agar riwayat tetap utuh.',
                ]);
            }

            DB::transaction(function () use ($account): void {
                $account->delete();
            });
        } finally {
            $tenantManager->restoreBaseConnection();
        }

        return back()->with('status', "Akun {$account->username} berhasil dihapus.");
    }

    public function updateCooperativeStatus(Request $request, PlatformCooperative $cooperative): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'confirm_action' => ['accepted'],
        ]);

        abort_unless(in_array($cooperative->status, ['active', 'inactive'], true), 422);

        $cooperative->update(['status' => $validated['status']]);

        return back()->with('status', "Koperasi {$cooperative->name} berhasil ".($cooperative->status === 'active' ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroyCooperative(Request $request, PlatformCooperative $cooperative): RedirectResponse
    {
        $validated = $request->validate([
            'cooperative_name_confirmation' => ['required', 'string', Rule::in([$cooperative->name])],
            'confirm_permanent_deletion' => ['accepted'],
        ]);
        $baseConnectionName = (string) config('fides.tenant_connection');
        $baseConnection = config("database.connections.{$baseConnectionName}");
        $tenantOverrides = $cooperative->connection_config ?? [];
        $driver = $tenantOverrides['driver'] ?? ($baseConnection['driver'] ?? null);
        $databaseName = $cooperative->database_name;
        $platformDatabaseName = config('database.connections.platform.database');

        if ($databaseName === ($baseConnection['database'] ?? null) || $databaseName === $platformDatabaseName) {
            throw ValidationException::withMessages([
                'cooperative_name_confirmation' => 'Database ini digunakan bersama oleh aplikasi atau katalog platform dan tidak dapat dihapus.',
            ]);
        }

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw ValidationException::withMessages([
                'cooperative_name_confirmation' => 'Jenis database koperasi ini tidak didukung untuk penghapusan permanen.',
            ]);
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            if (! is_string($databaseName) || preg_match('/\Afides_[a-z0-9_]+\z/', $databaseName) !== 1) {
                throw ValidationException::withMessages([
                    'cooperative_name_confirmation' => 'Nama database tidak sesuai pola tenant FIDES dan tidak dapat dihapus otomatis.',
                ]);
            }
        } else {
            $tenantDirectory = realpath(storage_path('fides-tenants'));
            $databasePath = realpath($databaseName);
            $databaseDirectory = realpath(dirname($databaseName));
            $databaseFileName = basename($databaseName);

            if (
                ! $tenantDirectory
                || $databaseDirectory !== $tenantDirectory
                || preg_match('/\Afides_[a-z0-9_]+\.sqlite\z/', $databaseFileName) !== 1
                || ($databasePath === false && $cooperative->status !== 'deletion_failed')
                || ($databasePath !== false && ! str_starts_with($databasePath, $tenantDirectory.DIRECTORY_SEPARATOR))
            ) {
                throw ValidationException::withMessages([
                    'cooperative_name_confirmation' => 'File database berada di luar direktori tenant FIDES atau tidak dapat diverifikasi.',
                ]);
            }
            $databasePath = $databasePath ?: $databaseName;
        }

        $errorKey = 'cooperative_delete.'.$cooperative->id;
        $cooperative->update(['status' => 'deleting']);
        DB::purge('fides_tenant');

        try {
            if ($driver === 'sqlite') {
                if (! File::delete($databasePath) && File::exists($databasePath)) {
                    throw new RuntimeException('File database tenant tidak dapat dihapus.');
                }
            } else {
                DB::connection($baseConnectionName)->statement('DROP DATABASE IF EXISTS `'.$databaseName.'`');
            }

            $cooperative->delete();
        } catch (QueryException|RuntimeException $exception) {
            $cooperative->update(['status' => 'deletion_failed']);
            Log::error('Permanent FIDES cooperative deletion failed.', [
                'cooperative_id' => $cooperative->id,
                'database_name' => $databaseName,
                'exception' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $errorKey => 'Penghapusan tenant gagal. Akses telah dinonaktifkan; periksa log sistem sebelum mencoba kembali.',
            ]);
        }

        return redirect()->route('platform.cooperatives')->with('status', "Koperasi {$validated['cooperative_name_confirmation']} dan seluruh databasenya telah dihapus permanen.");
    }

    public function updateProfilePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $admin = $request->user('platform');
        $admin->password = Hash::make($validated['password']);
        $admin->save();

        return back()->with('status', 'Password Superuser berhasil diperbarui.');
    }

    private function guardLastOwner(User $account, bool $remainsOwner): void
    {
        if ($account->role === 'manager' && ! $remainsOwner) {
            $ownerIds = User::query()->where('role', 'manager')->pluck('id');
            $disabledOwnerIds = PlatformAccountLink::query()
                ->where('cooperative_id', config('fides.current_tenant_id'))
                ->whereIn('tenant_user_id', $ownerIds)
                ->where('is_active', false)
                ->pluck('tenant_user_id');
            $activeOwnerCount = $ownerIds
                ->reject(fn ($ownerId): bool => (int) $ownerId === (int) $account->id || $disabledOwnerIds->contains($ownerId))
                ->count();

            if ($activeOwnerCount === 0) {
                throw ValidationException::withMessages([
                    'account' => 'Koperasi harus memiliki minimal satu akun Owner aktif.',
                ]);
            }
        }
    }

    private function accountHasHistory(User $account): bool
    {
        $userId = $account->id;

        foreach ([
            ['loans', 'created_by'],
            ['loans', 'approved_by'],
            ['payments', 'recorded_by'],
            ['payments', 'approved_by'],
            ['approval_requests', 'requested_by'],
            ['approval_requests', 'reviewed_by'],
            ['cash_transactions', 'created_by'],
            ['profit_transactions', 'created_by'],
            ['profit_transactions', 'user_id'],
            ['activity_logs', 'user_id'],
        ] as [$table, $column]) {
            if (DB::table($table)->where($column, $userId)->exists()) {
                return true;
            }
        }

        $borrower = $account->borrower;

        return $borrower && (
            $borrower->loans()->exists()
            || $borrower->advanceTransactions()->exists()
        );
    }

    /**
     * Load account-only summaries without querying tenant financial records.
     *
     * @param  Collection<int, PlatformCooperative>  $cooperatives
     * @return Collection<int, array{cooperative: PlatformCooperative, stats: array{users: int, borrowers: int, staff: int}, accounts: Collection}>
     */
    private function cooperativeOverviews(Collection $cooperatives): Collection
    {
        $tenantManager = app(FidesTenantManager::class);

        try {
            return $cooperatives->map(function (PlatformCooperative $cooperative) use ($tenantManager): array {
                $accounts = collect();
                $stats = ['users' => 0, 'borrowers' => 0, 'staff' => 0];

                if ($cooperative->status === 'active') {
                    $tenantManager->activate($cooperative);

                    $allAccounts = DB::table('users')
                        ->leftJoin('borrowers', 'borrowers.user_id', '=', 'users.id')
                        ->orderByRaw("CASE ".DB::connection()->getTablePrefix()."users.role WHEN 'manager' THEN 0 WHEN 'kasir' THEN 1 ELSE 2 END")
                        ->orderBy('users.username')
                        ->get([
                            'users.id',
                            'users.name',
                            'users.username',
                            'users.phone',
                            'users.role',
                            'users.last_login_at',
                            'users.must_change_password',
                            'borrowers.borrower_code',
                        ]);
                    $accounts = $allAccounts->whereIn('role', ['manager', 'kasir'])->values();
                    $activeAccounts = PlatformAccountLink::query()
                        ->where('cooperative_id', $cooperative->id)
                        ->pluck('is_active', 'tenant_user_id');
                    $accounts->each(function ($account) use ($activeAccounts): void {
                        $account->is_active = (bool) ($activeAccounts[$account->id] ?? true);
                    });
                    $stats = [
                        'users' => $allAccounts->count(),
                        'borrowers' => (int) DB::table('borrowers')->count(),
                        'staff' => $accounts->count(),
                    ];
                }

                return compact('cooperative', 'stats', 'accounts');
            });
        } finally {
            $tenantManager->restoreBaseConnection();
        }
    }
}
