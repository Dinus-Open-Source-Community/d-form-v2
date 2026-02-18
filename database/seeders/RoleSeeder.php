<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // users management
            'users.list',
            'users.create',
            'users.view',
            'users.edit',
            'users.delete',

            // roles management
            'roles.list',
            'roles.create',
            'roles.view',
            'roles.edit',
            'roles.delete',

            // permissions management
            'permissions.list',
            'permissions.create',
            'permissions.view',
            'permissions.edit',
            'permissions.delete',

            // events management
            'events.list',
            'events.create',
            'events.view',
            'events.edit',
            'events.delete',
            'events.join',

            // attendances management
            'attendances.list',
            'attendances.create',
            'attendances.view',
            'attendances.edit',
            'attendances.delete',
            'attendances.export',
            'attendances.import',

            // recruitments management
            // 'recruitments.list',
            // 'recruitments.create',
            // 'recruitments.view',
            // 'recruitments.edit',
            // 'recruitments.delete',

            // form submissions management
            'form_submissions.list',
            'form_submissions.create',
            'form_submissions.view',
            'form_submissions.edit',
            'form_submissions.delete'
        ];

        $roles = [
            'admin' => preg_grep("/^(?!users\.|permissions\.|roles\.|form_submissions\.create).+$/", $permissions),
            'member' => [
                'attendances.create',
                'form_submissions.list',
                'form_submissions.create',
                'form_submissions.view',
                'form_submissions.edit',
                'form_submissions.delete'
            ]
        ];

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // update cache to know about the newly created permissions (required if using WithoutModelEvents in seeders)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'admin'])
            ->givePermissionTo($roles['admin']);

        Role::create(['name' => 'member'])
            ->givePermissionTo($roles['member']);

        Role::create(['name' => 'super-admin'])
            ->givePermissionTo(Permission::all());
    }
}
