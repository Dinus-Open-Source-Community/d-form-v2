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
            RecruitmentDivisionSeeder::class,
            EventSeeder::class,
            FormSeeder::class,
            OprecFormSeeder::class,
        ]);

        // Dev/local only: periode oprec dummy agar /recruitment langsung bisa submit,
        // + data scan test. Prod TIDAK memakai seeder ini — cukup migrate
        // + RecruitmentDivisionSeeder + OprecFormSeeder, lalu period dibuat manual
        // via /admin/recruitment (RecruitmentPeriodService::create otomatis
        // membuat registration sequence).
        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call([
                RecruitmentPeriodSeeder::class,
                ScanTestSeeder::class,
            ]);
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'admin', 'password' => 'admin password'],
        );
        $admin->syncRoles(['admin']);

        if (env('APP_ENV') === 'local') {
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
