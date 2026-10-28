<?php

namespace App\Services;

use App\Models\PlatformCooperative;
use App\Models\ProfitBalance;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class FidesCooperativeProvisioner
{
    public function create(
        string $name,
        string $slug,
        string $ownerUsername,
        string $ownerPassword,
    ): array {
        $slug = $this->validatedSlug($slug);
        $databaseName = 'fides_'.str_replace('-', '_', $slug);
        $baseConnection = (string) config('fides.tenant_connection');
        $base = config("database.connections.{$baseConnection}");

        if (! is_array($base) || ! in_array($base['driver'] ?? null, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('Pembuatan koperasi otomatis hanya mendukung database MySQL/MariaDB atau SQLite.');
        }

        if (PlatformCooperative::query()->where('slug', $slug)->orWhere('database_name', $databaseName)->exists()) {
            throw new RuntimeException('Nama alamat atau database koperasi sudah digunakan.');
        }

        $connectionConfig = null;
        if ($base['driver'] === 'sqlite') {
            $databaseName = $this->createSqliteDatabase($databaseName);
            $connectionConfig = ['driver' => 'sqlite'];
        } else {
            $exists = DB::connection($baseConnection)->selectOne(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$databaseName],
            );

            if ($exists) {
                throw new RuntimeException('Database sudah ada; tidak ada data yang ditimpa.');
            }

            try {
                DB::connection($baseConnection)->statement(
                    'CREATE DATABASE `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                );
            } catch (QueryException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1044) {
                    throw new RuntimeException(
                        'Database koperasi gagal dibuat. Periksa koneksi database dan hak akses CREATE DATABASE.',
                        previous: $exception,
                    );
                }

                $databaseName = $this->createSqliteDatabase($databaseName);
                $connectionConfig = ['driver' => 'sqlite'];
            }
        }

        $cooperative = PlatformCooperative::query()->create([
            'name' => $name,
            'slug' => $slug,
            'database_name' => $databaseName,
            'connection_config' => $connectionConfig,
            'status' => 'provisioning',
        ]);

        try {
            app(FidesTenantManager::class)->activate($cooperative);
            $exitCode = Artisan::call('migrate', [
                '--database' => 'fides_tenant',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                throw new RuntimeException('Migrasi database koperasi gagal: '.trim(Artisan::output()));
            }

            $owner = User::query()->create([
                'name' => $name,
                'username' => $ownerUsername,
                'password' => Hash::make($ownerPassword),
                'role' => 'manager',
                'must_change_password' => true,
            ]);
            ProfitBalance::query()->firstOrCreate(['user_id' => $owner->id]);

            $adminPassword = Str::password(20);
            $admin = User::query()->create([
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make($adminPassword),
                'role' => 'kasir',
                'must_change_password' => true,
            ]);
            ProfitBalance::query()->firstOrCreate(['user_id' => $admin->id]);

            $cooperative->update(['status' => 'active']);

            return [
                'cooperative' => $cooperative,
                'admin_username' => $admin->username,
                'admin_password' => $adminPassword,
            ];
        } catch (\Throwable $exception) {
            $cooperative->update(['status' => 'provisioning_failed']);
            throw $exception;
        } finally {
            app(FidesTenantManager::class)->restoreBaseConnection();
        }
    }

    public function registerCurrent(string $name, string $slug): PlatformCooperative
    {
        $slug = $this->validatedSlug($slug);
        $connection = (string) config('fides.tenant_connection');
        $databaseName = config("database.connections.{$connection}.database");

        if (! is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('Nama database aktif tidak dapat ditentukan.');
        }

        $cooperative = PlatformCooperative::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'database_name' => $databaseName,
                'status' => 'active',
            ],
        );

        if ($cooperative->database_name !== $databaseName) {
            throw new RuntimeException('Slug sudah terdaftar dengan database yang berbeda.');
        }

        return $cooperative;
    }

    private function validatedSlug(string $slug): string
    {
        $slug = Str::slug(trim($slug));
        if ($slug === '' || strlen($slug) > 50 || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1) {
            throw new RuntimeException('Slug koperasi tidak valid.');
        }

        return $slug;
    }

    private function createSqliteDatabase(string $databaseName): string
    {
        $directory = storage_path('fides-tenants');
        File::ensureDirectoryExists($directory);
        $databasePath = $directory.'/'.$databaseName.'.sqlite';

        if (File::exists($databasePath)) {
            throw new RuntimeException('File database koperasi sudah ada; tidak ada data yang ditimpa.');
        }

        if (File::put($databasePath, '') === false) {
            throw new RuntimeException('File database koperasi gagal dibuat. Periksa izin tulis pada direktori storage.');
        }

        return $databasePath;
    }
}
