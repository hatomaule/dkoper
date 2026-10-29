<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\PlatformAccountLink;
use App\Models\PlatformCooperative;
use App\Models\Borrower;
use App\Models\User;
use App\Services\FidesAccountDirectory;
use App\Services\FidesTenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FidesTenantLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantPrefix;

    private string $platformPrefix;

    private array $tenantPrefixes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantPrefix = 'ft_';
        $this->tenantPrefixes = [$this->tenantPrefix];
        $this->platformPrefix = 'fp_';
        $mysql = config('database.connections.mysql');

        config([
            'database.connections.platform' => array_merge($mysql, ['prefix' => $this->platformPrefix]),
            'database.connections.fides_tenant' => array_merge($mysql, ['prefix' => $this->tenantPrefix]),
            'fides.enabled' => true,
        ]);
        DB::purge('platform');
        DB::purge('fides_tenant');

        $this->assertSame(0, Artisan::call('fides:install'));
        $cooperative = PlatformCooperative::query()->create([
            'name' => 'Koperasi Uji',
            'slug' => 'koperasi-uji',
            'database_name' => config('database.connections.mysql.database'),
            'connection_config' => ['prefix' => $this->tenantPrefix],
            'status' => 'active',
        ]);

        app(FidesTenantManager::class)->activate($cooperative);
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'fides_tenant',
            '--force' => true,
        ]));

        User::query()->create([
            'name' => 'Owner Uji',
            'username' => 'owner-uji',
            'password' => Hash::make('secure-password'),
            'role' => 'manager',
            'must_change_password' => false,
        ]);
    }

    protected function tearDown(): void
    {
        app(FidesTenantManager::class)->restoreBaseConnection();
        DB::purge('fides_tenant');
        DB::purge('platform');
        foreach ($this->tenantPrefixes as $prefix) {
            $this->dropPrefixedTables($prefix);
        }
        $this->dropPrefixedTables($this->platformPrefix);

        parent::tearDown();
    }

    public function test_login_finds_tenant_account_and_keeps_its_database_in_session(): void
    {
        $response = $this->post('/login', [
            'identifier' => 'owner-uji',
            'password' => 'secure-password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs(User::query()->where('username', 'owner-uji')->first());
        $this->assertNotNull(session(config('fides.session_tenant_key')));
        $this->assertSame($this->tenantPrefix, DB::connection()->getTablePrefix());
        $this->get('/dashboard')->assertOk();
        $this->assertSame($this->tenantPrefix, DB::connection()->getTablePrefix());
    }

    public function test_super_admin_uses_the_shared_login_and_reaches_platform_portal(): void
    {
        PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);

        $response = $this->post('/login', [
            'identifier' => 'fides-root',
            'password' => 'platform-secure-password',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticated('platform');
        $this->get(route('platform.dashboard'))
            ->assertOk()
            ->assertDontSee('Ringkasan koperasi')
            ->assertDontSee('Angka menunjukkan jumlahnya')
            ->assertSee('Akun pengguna')
            ->assertSee('Nasabah')
            ->assertSee('platform-coop-chart-bar is-users', false)
            ->assertSee('platform-coop-chart-bar is-borrowers', false)
            ->assertSee('<section class="space-y-4">', false);
        $this->get(route('platform.accounts'))
            ->assertOk()
            ->assertSee('Owner Uji')
            ->assertSee('Owner');
        $accountsPage = $this->get(route('platform.accounts', ['cooperative' => PlatformCooperative::query()->value('id')]));
        $accountsPage->assertOk()
            ->assertSee('data-list-view-mode="cards"', false)
            ->assertSee('role="switch"', false)
            ->assertSee('data-open-dialog="cooperative-status-'.PlatformCooperative::query()->value('id').'"', false)
            ->assertSee('name="confirm_action"', false)
            ->assertSee('name="confirm_account_action"', false)
            ->assertSee('data-platform-auto-refresh="60000"', false)
            ->assertSee('Manajemen akun')
            ->assertSee('Koperasi Uji')
            ->assertSee(route('platform.accounts', ['cooperative' => PlatformCooperative::query()->value('id')]), false)
            ->assertDontSee('aria-label="Pilih koperasi"', false)
            ->assertDontSee('Selamat datang');
        $this->assertSame(3, substr_count($accountsPage->getContent(), 'role="switch"'));
        $this->assertSame(2, substr_count($accountsPage->getContent(), 'data-open-dialog="status-account-'));
        $this->get(route('platform.cooperatives'))
            ->assertOk()
            ->assertSee('Daftarkan koperasi')
            ->assertSee('Koperasi Uji')
            ->assertSee('data-list-page-size', false)
            ->assertSee('data-list-view-mode="cards"', false)
            ->assertDontSee('Daftar koperasi')
            ->assertDontSee('ruang terdaftar')
            ->assertSee('data-open-dialog="register-cooperative"', false)
            ->assertSee('data-open-dialog="cooperative-delete-', false)
            ->assertSee('confirm_permanent_deletion', false)
            ->assertDontSee('Kelola ruang koperasi yang terpisah')
            ->assertDontSee('Manajemen Koperasi</h2>', false);
        $cooperative = PlatformCooperative::query()->firstOrFail();
        $this->get(route('platform.cooperatives.show', $cooperative))
            ->assertOk()
            ->assertSee('Koperasi Uji')
            ->assertSee('Total pengguna')
            ->assertDontSee('Saldo kas')
            ->assertDontSee('Pinjaman aktif');
        $this->assertSame(config('database.connections.mysql.database'), DB::connection()->getDatabaseName());
    }

    public function test_super_admin_cannot_change_an_account_role(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();
        $owner = User::query()->where('username', 'owner-uji')->firstOrFail();
        User::query()->create([
            'name' => 'Owner Cadangan',
            'username' => 'owner-cadangan',
            'password' => Hash::make('secure-password'),
            'role' => 'manager',
            'must_change_password' => false,
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->patch(route('platform.accounts.update', [$cooperative->id, $owner->id]), [
                'role' => 'kasir',
            ])
            ->assertRedirect();

        app(FidesTenantManager::class)->activate($cooperative);
        $updated = User::query()->findOrFail($owner->id);
        $this->assertSame('Owner Uji', $updated->name);
        $this->assertSame('owner-uji', $updated->username);
        $this->assertSame('manager', $updated->role);
        app(FidesTenantManager::class)->restoreBaseConnection();
    }

    public function test_account_delete_confirmation_uses_each_account_role(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        User::query()->create([
            'name' => 'Admin Uji',
            'username' => 'admin-uji',
            'password' => Hash::make('secure-password'),
            'role' => 'kasir',
            'must_change_password' => false,
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->get(route('platform.accounts'))
            ->assertOk()
            ->assertSee('Hapus Owner?')
            ->assertSee('Hapus Admin?');
    }

    public function test_super_admin_can_create_owner_or_admin_accounts(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();

        $this->actingAs($platformAdmin, 'platform')
            ->get(route('platform.accounts'))
            ->assertOk()
            ->assertSee('Create')
            ->assertSee('data-open-dialog="create-staff-account-'.$cooperative->id.'"', false)
            ->assertSee('Buat akun Owner/Admin')
            ->assertDontSee('Nasabah Uji')
            ->assertDontSee('Simpan peran')
            ->assertDontSee('aria-label="Peran akun"', false);

        $this->post(route('platform.accounts.store', $cooperative->id), [
            'name' => 'Admin Baru',
            'username' => 'admin-baru',
            'role' => 'kasir',
            'password' => 'initial-admin-password',
            'password_confirmation' => 'initial-admin-password',
        ])->assertRedirect()
            ->assertSessionHas('status');

        app(FidesTenantManager::class)->activate($cooperative);
        $admin = User::query()->where('username', 'admin-baru')->firstOrFail();
        $this->assertSame('kasir', $admin->role);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check('initial-admin-password', $admin->password));
        $this->assertDatabaseHas('platform_account_links', [
            'cooperative_id' => $cooperative->id,
            'tenant_user_id' => $admin->id,
            'username' => 'admin-baru',
            'is_active' => true,
        ], 'platform');
        app(FidesTenantManager::class)->restoreBaseConnection();
    }

    public function test_new_cooperatives_are_added_to_account_submenu(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $newCooperative = PlatformCooperative::query()->create([
            'name' => 'Koperasi Baru',
            'slug' => 'koperasi-baru',
            'database_name' => 'not-provisioned',
            'connection_config' => [],
            'status' => 'inactive',
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->get(route('platform.accounts', ['cooperative' => $newCooperative->id]))
            ->assertOk()
            ->assertSee('Koperasi Uji')
            ->assertSee('Koperasi Baru')
            ->assertSee('Pengelolaan akun tersedia setelah ruang koperasi aktif.');
    }

    public function test_super_admin_can_permanently_delete_a_separate_sqlite_cooperative_after_confirmation(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $databasePath = storage_path('fides-tenants/fides_hapus_uji.sqlite');
        File::ensureDirectoryExists(dirname($databasePath));
        File::put($databasePath, '');
        $cooperative = PlatformCooperative::query()->create([
            'name' => 'Koperasi Hapus Uji',
            'slug' => 'hapus-uji',
            'database_name' => $databasePath,
            'connection_config' => ['driver' => 'sqlite'],
            'status' => 'active',
        ]);
        $accountLink = PlatformAccountLink::query()->create([
            'cooperative_id' => $cooperative->id,
            'tenant_user_id' => 999,
            'username' => 'uji-hapus',
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->delete(route('platform.cooperatives.destroy', $cooperative), [
                'cooperative_name_confirmation' => 'Koperasi Hapus Uji',
                'confirm_permanent_deletion' => 'yes',
            ])
            ->assertRedirect(route('platform.cooperatives'));

        $this->assertDatabaseMissing('platform_cooperatives', ['id' => $cooperative->id], 'platform');
        $this->assertDatabaseMissing('platform_account_links', ['id' => $accountLink->id], 'platform');
        $this->assertFileDoesNotExist($databasePath);
    }

    public function test_permanent_cooperative_deletion_cannot_drop_the_application_database(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();

        $this->actingAs($platformAdmin, 'platform')
            ->delete(route('platform.cooperatives.destroy', $cooperative), [
                'cooperative_name_confirmation' => $cooperative->name,
                'confirm_permanent_deletion' => 'yes',
            ])
            ->assertSessionHasErrors('cooperative_name_confirmation');

        $this->assertDatabaseHas('platform_cooperatives', ['id' => $cooperative->id], 'platform');
        $this->assertSame(config('database.connections.mysql.database'), DB::connection()->getDatabaseName());
    }

    public function test_super_admin_can_update_owner_and_reset_the_tenant_password(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();
        $owner = User::query()->where('username', 'owner-uji')->firstOrFail();

        $this->actingAs($platformAdmin, 'platform')
            ->patch(route('platform.accounts.update', [$cooperative->id, $owner->id]), [
                'name' => 'Owner Diperbarui',
                'username' => 'owner-baru',
                'role' => 'manager',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect();

        app(FidesTenantManager::class)->activate($cooperative);
        $owner = User::query()->whereKey($owner->id)->firstOrFail();
        $this->assertSame('Owner Diperbarui', $owner->name);
        $this->assertSame('owner-baru', $owner->username);
        $this->assertTrue($owner->must_change_password);
        $this->assertTrue(Hash::check('new-secure-password', $owner->password));
        $this->assertDatabaseHas('platform_account_links', [
            'cooperative_id' => $cooperative->id,
            'tenant_user_id' => $owner->id,
            'username' => 'owner-baru',
        ], 'platform');
        app(FidesTenantManager::class)->restoreBaseConnection();
    }

    public function test_super_admin_can_reset_an_account_password_from_account_management(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();
        $owner = User::query()->where('username', 'owner-uji')->firstOrFail();

        $this->actingAs($platformAdmin, 'platform')
            ->post(route('platform.accounts.reset-password', [$cooperative->id, $owner->id]), [
                'password' => 'reset-owner-password',
                'password_confirmation' => 'reset-owner-password',
                'confirm_account_action' => 'yes',
            ])
            ->assertRedirect();

        app(FidesTenantManager::class)->activate($cooperative);
        $owner = User::query()->findOrFail($owner->id);
        $this->assertTrue(Hash::check('reset-owner-password', $owner->password));
        $this->assertTrue($owner->must_change_password);
        app(FidesTenantManager::class)->restoreBaseConnection();
    }

    public function test_super_admin_can_toggle_cooperative_access_with_confirmation(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();

        $this->actingAs($platformAdmin, 'platform')
            ->patch(route('platform.cooperatives.status', $cooperative), [
                'status' => 'inactive',
                'confirm_action' => 'yes',
            ])
            ->assertRedirect();
        $this->assertCount(0, app(FidesAccountDirectory::class)->candidates('owner-uji'));
        $this->assertDatabaseHas('platform_cooperatives', [
            'id' => $cooperative->id,
            'status' => 'inactive',
        ], 'platform');

        $this->patch(route('platform.cooperatives.status', $cooperative), [
            'status' => 'active',
            'confirm_action' => 'yes',
        ])->assertRedirect();
        $this->assertCount(1, app(FidesAccountDirectory::class)->candidates('owner-uji'));
        $this->assertDatabaseHas('platform_cooperatives', [
            'id' => $cooperative->id,
            'status' => 'active',
        ], 'platform');
    }

    public function test_super_admin_cannot_view_or_manage_borrower_accounts(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $borrowerUser = User::query()->create([
            'name' => 'Nasabah Uji',
            'email' => 'nasabah@example.test',
            'username' => 'nasabah-uji',
            'phone' => '081234567801',
            'password' => Hash::make('borrower-password'),
            'role' => 'nasabah',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::query()->create([
            'user_id' => $borrowerUser->id,
            'borrower_code' => 'BR-0001',
            'full_name' => $borrowerUser->name,
            'phone' => $borrowerUser->phone,
            'status' => 'active',
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();

        $this->actingAs($platformAdmin, 'platform')
            ->get(route('platform.accounts'))
            ->assertOk()
            ->assertDontSee('Nasabah Uji')
            ->assertDontSee('BR-0001');

        $this->patch(route('platform.accounts.status', [$cooperative->id, $borrowerUser->id]), [
            'is_active' => 0,
            'confirm_account_action' => 'yes',
        ])->assertNotFound();

        $this->delete(route('platform.accounts.destroy', [$cooperative->id, $borrowerUser->id]), [
            'confirm_account_action' => 'yes',
        ])->assertNotFound();
        $this->assertDatabaseHas('platform_account_links', [
            'cooperative_id' => $cooperative->id,
            'tenant_user_id' => $borrowerUser->id,
            'is_active' => true,
        ], 'platform');
    }

    public function test_account_deletion_preserves_users_with_financial_history(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);
        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();

        $safeUser = User::query()->create([
            'name' => 'Admin Hapus',
            'username' => 'admin-hapus',
            'password' => Hash::make('admin-password'),
            'role' => 'kasir',
            'must_change_password' => false,
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->delete(route('platform.accounts.destroy', [$cooperative->id, $safeUser->id]), [
                'confirm_account_action' => 'yes',
            ])
            ->assertRedirect();
        $this->assertDatabaseMissing('platform_account_links', [
            'cooperative_id' => $cooperative->id,
            'tenant_user_id' => $safeUser->id,
        ], 'platform');

        app(FidesTenantManager::class)->activate($cooperative);
        $this->assertDatabaseMissing('users', ['id' => $safeUser->id], 'fides_tenant');

        $protectedUser = User::query()->create([
            'name' => 'Admin Beraktivitas',
            'username' => 'admin-beraktivitas',
            'password' => Hash::make('admin-password'),
            'role' => 'kasir',
            'must_change_password' => false,
        ]);
        $borrower = Borrower::query()->create([
            'borrower_code' => 'BR-PROTECTED',
            'full_name' => 'Nasabah untuk uji',
            'status' => 'active',
        ]);
        app(\App\Services\LoanService::class)->createLoan($protectedUser, $borrower->id, 500_000);
        app(FidesTenantManager::class)->restoreBaseConnection();

        $this->delete(route('platform.accounts.destroy', [$cooperative->id, $protectedUser->id]), [
            'confirm_account_action' => 'yes',
        ])
            ->assertSessionHasErrors('account');

        app(FidesTenantManager::class)->activate($cooperative);
        $this->assertDatabaseHas('users', ['id' => $protectedUser->id], 'fides_tenant');
        app(FidesTenantManager::class)->restoreBaseConnection();
    }

    public function test_superuser_can_change_own_password_from_profile_menu_action(): void
    {
        $platformAdmin = PlatformAdmin::query()->create([
            'name' => 'FIDES Admin',
            'username' => 'fides-root',
            'password' => Hash::make('platform-secure-password'),
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee('Ganti password')
            ->assertSee('id="platform-password-dialog"', false);

        $this->post(route('platform.profile.password'), [
            'current_password' => 'platform-secure-password',
            'password' => 'new-platform-password',
            'password_confirmation' => 'new-platform-password',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('new-platform-password', $platformAdmin->fresh()->password));
    }

    public function test_account_link_index_is_updated_when_tenant_user_is_saved(): void
    {
        $user = User::query()->where('username', 'owner-uji')->firstOrFail();

        $this->assertDatabaseHas('platform_account_links', [
            'cooperative_id' => PlatformCooperative::query()->value('id'),
            'tenant_user_id' => $user->id,
            'username' => 'owner-uji',
        ], 'platform');

        $user->update(['phone' => '081234567890']);

        $this->assertDatabaseHas('platform_account_links', [
            'tenant_user_id' => $user->id,
            'phone' => '081234567890',
        ], 'platform');
    }

    public function test_same_login_can_choose_between_matching_cooperative_accounts(): void
    {
        $secondCooperative = PlatformCooperative::query()->create([
            'name' => 'Koperasi Kedua',
            'slug' => 'koperasi-kedua',
            'database_name' => 'unprovisioned_test_database',
            'status' => 'active',
        ]);
        PlatformAccountLink::query()->create([
            'cooperative_id' => $secondCooperative->id,
            'tenant_user_id' => 999,
            'username' => 'owner-uji',
        ]);
        $firstCooperative = PlatformCooperative::query()->where('slug', 'koperasi-uji')->firstOrFail();
        $links = PlatformAccountLink::query()
            ->whereIn('cooperative_id', [$firstCooperative->id, $secondCooperative->id])
            ->with('cooperative')
            ->get();
        $tenantUser = User::query()->where('username', 'owner-uji')->firstOrFail();
        $tenantManager = \Mockery::mock(app(FidesTenantManager::class))->makePartial();
        $tenantManager->shouldReceive('activate')->andReturnUsing(function (PlatformCooperative $cooperative): void {
            $connection = array_merge(config('database.connections.mysql'), ['prefix' => $this->tenantPrefix]);
            config([
                'database.connections.fides_tenant' => $connection,
                'database.default' => 'fides_tenant',
                'fides.current_tenant_id' => $cooperative->id,
            ]);
            DB::purge('fides_tenant');
            DB::setDefaultConnection('fides_tenant');
        });
        $this->instance(FidesTenantManager::class, $tenantManager);
        $directory = \Mockery::mock(FidesAccountDirectory::class);
        $directory->shouldReceive('candidates')->once()->andReturn($links);
        $directory->shouldReceive('authenticate')
            ->times(3)
            ->andReturnUsing(function (string $identifier, string $password, int $cooperativeId) use ($tenantUser) {
                $cooperative = PlatformCooperative::query()->findOrFail($cooperativeId);
                app(FidesTenantManager::class)->activate($cooperative);

                return $tenantUser;
            });
        $directory->shouldReceive('syncUser')->once();
        $this->instance(FidesAccountDirectory::class, $directory);

        $this->post('/login', [
            'identifier' => 'owner-uji',
            'password' => 'secure-password',
        ])->assertRedirect(route('login.cooperative.select'));

        $this->get(route('login.cooperative.select'))
            ->assertOk()
            ->assertSee('Koperasi Uji')
            ->assertSee('Koperasi Kedua');

        $this->post(route('login.cooperative.authenticate'), [
            'cooperative_id' => $secondCooperative->id,
            'password' => 'secure-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame($secondCooperative->id, session(config('fides.session_tenant_key')));
    }

    private function dropPrefixedTables(string $prefix): void
    {
        $connection = DB::connection('mysql');
        $tables = array_map(function ($row) {
            $values = (array) $row;

            return reset($values);
        }, $connection->select('SHOW TABLES'));
        $matching = array_values(array_filter($tables, fn ($table) => str_starts_with($table, $prefix)));

        if ($matching === []) {
            return;
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($matching as $table) {
            $connection->statement('DROP TABLE `'.$table.'`');
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
