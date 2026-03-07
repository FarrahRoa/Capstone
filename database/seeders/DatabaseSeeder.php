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
        $this->call([
            RoleSeeder::class,
            SpaceSeeder::class,
        ]);

        $adminRole = \App\Models\Role::where('slug', 'admin')->first();
        if ($adminRole && !User::where('email', 'admin@xu.edu.ph')->exists()) {
            User::factory()->create([
                'name' => 'Library Admin',
                'email' => 'admin@xu.edu.ph',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_activated' => true,
            ]);
        }
    }
}
