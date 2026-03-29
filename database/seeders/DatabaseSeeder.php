<?php

namespace Database\Seeders;

use App\Models\PolicyDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo accounts (password: "password", all pre-activated for local/thesis demos):
     * admin@xu.edu.ph, demo.student@my.xu.edu.ph, demo.faculty@xu.edu.ph, demo.staff@xu.edu.ph,
     * demo.assistant@my.xu.edu.ph, demo.librarian@xu.edu.ph
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SpaceSeeder::class,
        ]);

        PolicyDocument::reservationGuidelines();

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && ! User::where('email', 'admin@xu.edu.ph')->exists()) {
            User::factory()->create([
                'name' => 'Library Admin',
                'email' => 'admin@xu.edu.ph',
                'password' => bcrypt('password'),
                'role_id' => $adminRole->id,
                'is_activated' => true,
            ]);
        }

        $demoUsers = [
            ['email' => 'demo.student@my.xu.edu.ph', 'name' => 'Demo Student', 'slug' => 'student'],
            ['email' => 'demo.faculty@xu.edu.ph', 'name' => 'Demo Faculty', 'slug' => 'faculty'],
            ['email' => 'demo.staff@xu.edu.ph', 'name' => 'Demo Staff', 'slug' => 'staff'],
            ['email' => 'demo.assistant@my.xu.edu.ph', 'name' => 'Demo Student Assistant', 'slug' => 'student_assistant'],
            ['email' => 'demo.librarian@xu.edu.ph', 'name' => 'Demo Librarian', 'slug' => 'librarian'],
        ];

        foreach ($demoUsers as $row) {
            if (User::where('email', $row['email'])->exists()) {
                continue;
            }
            $role = Role::where('slug', $row['slug'])->first();
            if (! $role) {
                continue;
            }
            User::factory()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => bcrypt('password'),
                'role_id' => $role->id,
                'is_activated' => true,
            ]);
        }
    }
}
