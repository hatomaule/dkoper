<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BorrowerService
{
    public function createWithAccount(User $actor, string $fullName, string $phone): Borrower
    {
        abort_unless($actor->isManager(), 403);

        return DB::transaction(function () use ($actor, $fullName, $phone) {
            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($fullName))[0] ?? 'nasabah'));
            $base = strlen($base) < 4 ? str_pad($base, 4, 'x') : $base;

            $username = $base;
            $suffix = 1;
            while (User::where('username', $username)->exists()) {
                $username = $base . $suffix;
                $suffix++;
            }

            $user = User::create([
                'name' => $fullName,
                'username' => $username,
                'phone' => $phone,
                'role' => 'nasabah',
                'password' => Hash::make('123456'),
                'must_change_password' => true,
            ]);

            $borrower = Borrower::create([
                'user_id' => $user->id,
                'borrower_code' => 'TEMP_' . Str::uuid(),
                'full_name' => $fullName,
                'phone' => $phone,
                'status' => 'active',
            ]);
            $borrower->update(['borrower_code' => 'DK_' . str_pad((string) $borrower->id, 3, '0', STR_PAD_LEFT)]);

            ActivityLog::create([
                'user_id' => $actor->id,
                'action' => 'borrower.created',
                'entity_type' => Borrower::class,
                'entity_id' => $borrower->id,
                'metadata' => ['borrower_code' => $borrower->borrower_code],
                'ip_address' => request()?->ip(),
            ]);

            return $borrower;
        });
    }
}
