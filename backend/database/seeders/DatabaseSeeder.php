<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Set vicentejmn80@gmail.com as initial super_admin
        $user = User::where('email', 'vicentejmn80@gmail.com')->first();
        if ($user) {
            $user->role = 'super_admin';
            $user->save();
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
