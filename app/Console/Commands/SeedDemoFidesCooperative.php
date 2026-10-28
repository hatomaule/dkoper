<?php

namespace App\Console\Commands;

use App\Models\Borrower;
use App\Models\PlatformCooperative;
use App\Models\User;
use App\Services\FidesCooperativeProvisioner;
use App\Services\FidesTenantManager;
use App\Services\LoanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SeedDemoFidesCooperative extends Command
{
    protected $signature = 'fides:seed-demo';

    protected $description = 'Create an isolated Cair Dana demo cooperative with 21 sample borrowers and loans';

    public function handle(FidesCooperativeProvisioner $provisioner, LoanService $loans): int
    {
        if (PlatformCooperative::query()->where('slug', 'cair-dana')->exists()) {
            $this->error('Ruang cair-dana sudah terdaftar. Tidak ada data yang diubah.');

            return self::FAILURE;
        }

        $ownerPassword = Str::password(24);
        $result = $provisioner->create('Cair Dana', 'cair-dana', 'cairdana', $ownerPassword);
        $cooperative = $result['cooperative'];
        $tenantManager = app(FidesTenantManager::class);
        $tenantManager->activate($cooperative);

        try {
            DB::transaction(function () use ($loans): void {
                $owner = User::query()->where('username', 'cairdana')->firstOrFail();
                $password = Str::password(24);
                $principals = [
                    350_000, 500_000, 650_000, 800_000, 950_000, 1_100_000, 1_250_000,
                    1_400_000, 1_550_000, 1_700_000, 1_850_000, 2_000_000, 2_150_000,
                    2_300_000, 2_450_000, 2_600_000, 2_750_000, 2_900_000, 3_050_000,
                    3_200_000, 3_500_000,
                ];

                foreach ($principals as $index => $principal) {
                    $sequence = $index + 1;
                    $username = 'cairdana'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
                    $user = User::query()->create([
                        'name' => 'Nasabah Uji '.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
                        'email' => $username.'@cair-dana.example.test',
                        'username' => $username,
                        'phone' => '628100000'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                        'password' => $password,
                        'role' => 'nasabah',
                        'must_change_password' => true,
                    ]);
                    $borrower = Borrower::query()->create([
                        'user_id' => $user->id,
                        'borrower_code' => 'CD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                        'full_name' => $user->name,
                        'phone' => $user->phone,
                        'address' => 'Alamat contoh '.$sequence.', Cair Dana',
                        'status' => 'active',
                    ]);

                    $loans->createApprovedLoan($owner, $borrower->id, $principal);
                }
            });
        } catch (Throwable $exception) {
            $cooperative->update(['status' => 'provisioning_failed']);
            $this->error('Data demo gagal dibuat; ruang ditandai gagal agar tidak digunakan: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $tenantManager->restoreBaseConnection();
        }

        $this->info('Koperasi demo Cair Dana dibuat di tenant terisolasi, tanpa mengubah database DKoper.');
        $this->line('Username Owner: cairdana');
        $this->line('Password awal Owner: '.$ownerPassword);
        $this->line('Username Admin: '.$result['admin_username']);
        $this->line('Password awal Admin: '.$result['admin_password']);
        $this->line('Nasabah uji: 21 · pinjaman aktif: 21 · nominal pokok: Rp350.000–Rp3.500.000.');
        $this->warn('Password Owner dan Admin wajib diganti saat login pertama kali.');

        return self::SUCCESS;
    }
}
