<?php

namespace App\Services;

use App\Models\PlatformCooperative;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FidesTenantManager
{
    private string $baseConnection;

    public function __construct()
    {
        $this->baseConnection = (string) config('fides.tenant_connection');
    }

    public function activate(PlatformCooperative $cooperative): void
    {
        if (! in_array($cooperative->status, ['active', 'provisioning'], true)) {
            throw new RuntimeException('Koperasi tidak aktif.');
        }

        $base = config("database.connections.{$this->baseConnection}");

        if (! is_array($base)) {
            throw new RuntimeException('Koneksi dasar database koperasi tidak tersedia.');
        }

        $tenantOverrides = $cooperative->connection_config ?? [];
        $connection = ($tenantOverrides['driver'] ?? null) === 'sqlite'
            ? array_merge(config('database.connections.sqlite'), $tenantOverrides)
            : array_merge($base, $tenantOverrides);

        $connection['database'] = $cooperative->database_name;
        if ($connection['driver'] === 'sqlite') {
            unset($connection['url']);
        }

        config([
            'database.connections.fides_tenant' => $connection,
            'database.default' => 'fides_tenant',
            'fides.current_tenant_id' => $cooperative->id,
        ]);

        DB::purge('fides_tenant');
        DB::setDefaultConnection('fides_tenant');
    }

    public function restoreBaseConnection(): void
    {
        DB::purge('fides_tenant');

        config([
            'database.default' => $this->baseConnection,
            'database.connections.fides_tenant' => null,
            'fides.current_tenant_id' => null,
        ]);

        DB::setDefaultConnection($this->baseConnection);
    }
}
