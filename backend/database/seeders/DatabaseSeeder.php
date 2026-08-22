<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Buscar o crear el usuario Super Admin con contraseña 'admin123'
        User::updateOrCreate(
            ['email' => 'vicentejmn80@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('vjmn0211'),
                'role' => 'super_admin',
                'onboarding_completed' => true,
            ]
        );

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}