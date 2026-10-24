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
    public function test_staff_seeder_creates_alan_and_chalo(): void
    {
        $this->seed(\Database\Seeders\StaffAccountSeeder::class);

        $this->assertDatabaseHas('users', ['username' => 'alan', 'role' => 'manager']);
        $this->assertDatabaseHas('users', ['username' => 'chalo', 'role' => 'kasir']);
    }
}
