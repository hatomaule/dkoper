<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BorrowerService
{
    public function createWithAccount(string $fullName, string $phone): Borrower
    {
        return DB::transaction(function () use ($fullName, $phone) {
            $number = (int) Borrower::count() + 1;
            $borrowerCode = 'DK_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);

            $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($fullName))[0] ?? 'nasabah'));
            $base = strlen($base) < 4 ? str_pad($base, 4, 'x') : $base;

            $username = $base;
            $suffix = $number;
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

            return Borrower::create([
                'user_id' => $user->id,
                'borrower_code' => $borrowerCode,
                'full_name' => $fullName,
                'phone' => $phone,
                'status' => 'active',
            ]);
        });
    }
}
