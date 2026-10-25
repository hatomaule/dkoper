<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StaffAccountSeederTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_staff_seeder_creates_owner_and_admin(): void
    {
        $this->seed(\Database\Seeders\StaffAccountSeeder::class);

        $this->assertDatabaseHas('users', ['username' => 'owner', 'role' => 'manager']);
        $this->assertDatabaseHas('users', ['username' => 'admin', 'role' => 'kasir']);
    }
}
