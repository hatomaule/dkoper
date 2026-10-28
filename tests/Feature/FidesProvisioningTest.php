<?php

namespace Tests\Feature;

use App\Models\PlatformCooperative;
use App\Models\User;
use App\Services\FidesCooperativeProvisioner;
use App\Services\FidesTenantManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class FidesProvisioningTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().'/dkoper-fides-'.bin2hex(random_bytes(8));
        $storage = $this->temporaryRoot.'/storage';
        File::ensureDirectoryExists($storage);
        $this->app->useStoragePath($storage);

        config([
            'database.default' => 'mysql',
            'database.connections.platform' => array_merge(config('database.connections.sqlite'), [
                'database' => $this->temporaryRoot.'/platform.sqlite',
            ]),
            'database.connections.fides_provisioning_test' => array_merge(config('database.connections.sqlite'), [
                'database' => ':memory:',
            ]),
            'fides.enabled' => true,
            'fides.tenant_connection' => 'fides_provisioning_test',
        ]);
        DB::purge('platform');
        DB::purge('fides_provisioning_test');

        $this->assertSame(0, Artisan::call('fides:install'));
    }

    protected function tearDown(): void
    {
        app(FidesTenantManager::class)->restoreBaseConnection();
        DB::purge('platform');
        DB::purge('fides_provisioning_test');
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_provisioning_creates_two_independent_sqlite_cooperatives_with_forced_password_change(): void
    {
        $provisioner = app(FidesCooperativeProvisioner::class);
        $first = $provisioner->create('Koperasi Satu', 'koperasi-satu', 'owner-satu', 'secure-password');
        $second = $provisioner->create('Koperasi Dua', 'koperasi-dua', 'owner-dua', 'secure-password');

        $this->assertNotSame(
            $first['cooperative']->database_name,
            $second['cooperative']->database_name,
        );
        $this->assertSame('active', $first['cooperative']->fresh()->status);
        $this->assertSame('active', $second['cooperative']->fresh()->status);
        $this->assertSame('admin', $first['admin_username']);
        $this->assertNotSame($first['admin_password'], $second['admin_password']);

        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($first['cooperative']);
        $owner = User::query()->where('username', 'owner-satu')->firstOrFail();
        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $this->assertTrue($owner->must_change_password);
        $this->assertTrue(Hash::check('secure-password', $owner->password));
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check($first['admin_password'], $admin->password));

        Schema::connection('fides_tenant')->create('tenant_isolation_markers', function (Blueprint $table): void {
            $table->string('marker');
        });
        DB::connection('fides_tenant')->table('tenant_isolation_markers')->insert(['marker' => 'first']);

        $tenantManager->activate($second['cooperative']);
        $this->assertFalse(Schema::connection('fides_tenant')->hasTable('tenant_isolation_markers'));
        $this->assertSame(2, User::query()->count());

        $tenantManager->activate($first['cooperative']);
        $this->assertSame('first', DB::connection('fides_tenant')->table('tenant_isolation_markers')->value('marker'));

        $tenantManager->restoreBaseConnection();
        $this->assertSame('fides_provisioning_test', DB::getDefaultConnection());
        $this->assertNull(config('fides.current_tenant_id'));
        $this->assertNull(config('database.connections.fides_tenant'));
    }

    public function test_provisioning_refuses_to_overwrite_an_existing_tenant_database_file(): void
    {
        $databasePath = storage_path('fides-tenants/fides_koperasi_uji.sqlite');
        File::ensureDirectoryExists(dirname($databasePath));
        File::put($databasePath, 'existing database sentinel');

        try {
            app(FidesCooperativeProvisioner::class)->create(
                'Koperasi Uji',
                'koperasi-uji',
                'owner-uji',
                'secure-password',
            );
            $this->fail('Provisioning must not replace an existing tenant database file.');
        } catch (RuntimeException $exception) {
            $this->assertSame('File database koperasi sudah ada; tidak ada data yang ditimpa.', $exception->getMessage());
        }

        $this->assertSame('existing database sentinel', File::get($databasePath));
        $this->assertDatabaseCount('platform_cooperatives', 0, 'platform');
    }

    public function test_mysql_create_database_permission_error_falls_back_to_an_isolated_sqlite_tenant(): void
    {
        config([
            'database.connections.fides_provisioning_test' => array_merge(config('database.connections.mysql'), [
                'database' => 'unused_test_database',
            ]),
        ]);
        DB::purge('fides_provisioning_test');

        $pdoException = new \PDOException('Access denied');
        $pdoException->errorInfo = ['42000', 1044, 'Access denied'];
        $queryException = new QueryException(
            'fides_provisioning_test',
            'CREATE DATABASE `fides_koperasi_tanpa_izin`',
            [],
            $pdoException,
        );
        $connection = new class($queryException) extends Connection
        {
            public function __construct(private QueryException $queryException)
            {
                parent::__construct(null);
            }

            public function selectOne($query, $bindings = [], $useReadPdo = true)
            {
                return null;
            }

            public function statement($query, $bindings = [])
            {
                throw $this->queryException;
            }
        };
        DB::extend('fides_provisioning_test', fn (array $config, string $name) => $connection);

        $result = app(FidesCooperativeProvisioner::class)->create(
            'Koperasi Tanpa Izin MySQL',
            'koperasi-tanpa-izin',
            'owner-tanpa-izin',
            'secure-password',
        );
        $cooperative = $result['cooperative']->fresh();

        $this->assertSame('active', $cooperative->status);
        $this->assertSame('sqlite', $cooperative->connection_config['driver']);
        $this->assertFileExists($cooperative->database_name);

        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($cooperative);
        $this->assertSame(2, User::query()->count());
        $tenantManager->restoreBaseConnection();
    }

    public function test_failed_migration_leaves_a_visible_failed_cooperative_record(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', [
                '--database' => 'fides_tenant',
                '--force' => true,
                '--no-interaction' => true,
            ])
            ->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn('simulated migration error');

        try {
            app(FidesCooperativeProvisioner::class)->create(
                'Koperasi Gagal',
                'koperasi-gagal',
                'owner-gagal',
                'secure-password',
            );
            $this->fail('A failed tenant migration must not report successful provisioning.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Migrasi database koperasi gagal: simulated migration error',
                $exception->getMessage(),
            );
        }

        $cooperative = PlatformCooperative::query()->where('slug', 'koperasi-gagal')->firstOrFail();
        $this->assertSame('provisioning_failed', $cooperative->status);
        $this->assertFileExists($cooperative->database_name);
        $this->assertNull(config('database.connections.fides_tenant'));
    }

    public function test_provisioning_rejects_an_empty_sanitized_slug_without_creating_data(): void
    {
        try {
            app(FidesCooperativeProvisioner::class)->create(
                'Koperasi Uji',
                '---',
                'owner-uji',
                'secure-password',
            );
            $this->fail('An empty sanitized slug must not create a tenant.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Slug koperasi tidak valid.', $exception->getMessage());
        }

        $this->assertDatabaseCount('platform_cooperatives', 0, 'platform');
        $this->assertDirectoryDoesNotExist(storage_path('fides-tenants'));
    }

    public function test_demo_cooperative_seeds_twenty_one_varied_loans_without_touching_other_tenants(): void
    {
        $existing = app(FidesCooperativeProvisioner::class)->create(
            'Koperasi Nyata',
            'koperasi-nyata',
            'owner-nyata',
            'secure-password',
        );

        $this->assertSame(0, Artisan::call('fides:seed-demo'));

        $demo = PlatformCooperative::query()->where('slug', 'cair-dana')->firstOrFail();
        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($demo);
        $this->assertSame(23, User::query()->count());
        $this->assertSame(21, DB::table('borrowers')->count());
        $this->assertSame(21, DB::table('loans')->count());
        $this->assertSame(21, DB::table('loans')->where('status', 'active')->count());
        $this->assertGreaterThan(
            1,
            DB::table('loans')->distinct()->count('principal_amount'),
        );

        $tenantManager->activate($existing['cooperative']);
        $this->assertSame(2, User::query()->count());
        $this->assertSame(0, DB::table('borrowers')->count());

        $tenantManager->restoreBaseConnection();
        $this->assertSame(1, Artisan::call('fides:seed-demo'));
        $tenantManager->activate($demo);
        $this->assertSame(21, DB::table('borrowers')->count());
    }
}
