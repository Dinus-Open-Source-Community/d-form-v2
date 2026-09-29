<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed users only (admin, super-admin, members).
     *
     * Roles come from RoleSeeder; run it first when they don't exist yet
     * so this seeder also works standalone.
     */
    public function run(): void
    {
        if (!Role::query()->where('guard_name', 'web')->exists()) {
            $this->call(RoleSeeder::class);
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'admin', 'password' => 'admin password'],
        );
        $admin->syncRoles(['admin']);

        if (app()->environment('local')) {
            $admin2 = User::query()->firstOrCreate(
                ['email' => 'admin2@gmail.com'],
                ['name' => 'admin 2', 'password' => 'admin2 password']
            );
            $admin2->syncRoles(['admin']);
        }

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            ['name' => 'super admin', 'password' => 'superadmin password'],
        );
        $superAdmin->syncRoles(['super-admin']);

        $memberData = [
            ['name' => 'Ahmad Fauzi', 'email' => 'ahmad@student.dinus.ac.id'],
            ['name' => 'Siti Nurhaliza', 'email' => 'siti@student.dinus.ac.id'],
            ['name' => 'Budi Santoso', 'email' => 'budi@student.dinus.ac.id'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@student.dinus.ac.id'],
            ['name' => 'Rizky Pratama', 'email' => 'rizky@student.dinus.ac.id'],
        ];

        foreach ($memberData as $data) {
            $member = User::query()->firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => 'password'],
            );
            $member->syncRoles(['member']);
        }
    }
}
