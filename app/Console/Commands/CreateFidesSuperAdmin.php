<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateFidesSuperAdmin extends Command
{
    protected $signature = 'fides:make-super-admin {username} {name} {--email=}';

    protected $description = 'Create a FIDES Super Admin account';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $name = (string) $this->argument('name');
        $email = $this->option('email');

        if (PlatformAdmin::query()->where('username', $username)->exists()) {
            $this->error('Username Super Admin sudah digunakan.');

            return self::FAILURE;
        }

        $password = $this->secret('Password Super Admin');

        if (! $password || strlen($password) < 12) {
            $this->error('Password harus memiliki minimal 12 karakter.');

            return self::FAILURE;
        }

        PlatformAdmin::query()->create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('Akun Super Admin berhasil dibuat di database pusat FIDES.');

        return self::SUCCESS;
    }
}
