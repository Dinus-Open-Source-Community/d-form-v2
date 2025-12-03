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
        // User::factory(10)->create();

        // User::factory(100)->create();

        // testing user
        // User::create([
        //     'name' => 'user 1',
        //     'email' => 'user@gmail.com',
        //     'password' => 'password',
        // ]);

        // testing admin
        $admin = User::create([
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => 'admin password'
        ]);

        $admin->assignRole('admin');
    }
}
