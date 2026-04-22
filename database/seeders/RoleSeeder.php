<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full access including approval and reports'],
            ['name' => 'Employee', 'slug' => 'faculty', 'description' => 'Employee @xu.edu.ph – full reservation access'],
            ['name' => 'Staff', 'slug' => 'staff', 'description' => 'Staff @xu.edu.ph – full reservation access'],
            ['name' => 'Librarian', 'slug' => 'librarian', 'description' => 'Librarian @xu.edu.ph – full reservation access'],
            ['name' => 'Student', 'slug' => 'student', 'description' => 'Student @my.xu.edu.ph – full reservation access'],
            ['name' => 'Student Assistant', 'slug' => 'student_assistant', 'description' => 'Queue and approvals @my.xu.edu.ph (assigned by admin)'],
        ];
        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
