<?php

namespace Database\Seeders;

use App\Models\ProfitBalance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffAccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Alan', 'username' => 'alan', 'role' => 'manager'],
            ['name' => 'Chalo', 'username' => 'chalo', 'role' => 'kasir'],
        ] as $staff) {
            $user = User::updateOrCreate(
                ['username' => $staff['username']],
                [
                    'name' => $staff['name'],
                    'email' => null,
                    'password' => Hash::make('123456'),
                    'role' => $staff['role'],
                    'must_change_password' => true,
                ]
            );

            ProfitBalance::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
