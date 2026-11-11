<?php

namespace Tests\Feature\Broadcasting;

use App\Models\EmailBroadcast;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailBroadcastPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['features.broadcast' => true]);
    }

    public function test_audit_p2_012_seeded_roles_never_grant_update_without_create(): void
    {
        $broadcast = new EmailBroadcast();

        foreach (Role::query()->get() as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            self::assertSame(
                $user->can('email-broadcast.create'),
                $user->can('update', $broadcast),
                "Role {$role->name} dapat update tanpa create.",
            );
            self::assertSame(
                $user->can('email-broadcast.schedule'),
                $user->can('schedule', $broadcast),
                "Role {$role->name} tidak konsisten antara permission dan policy schedule.",
            );
        }
    }

    public function test_audit_p2_012_seeded_admin_roles_keep_broadcast_access(): void
    {
        $broadcast = new EmailBroadcast();

        foreach (['admin', 'super-admin'] as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            self::assertTrue($user->can('viewAny', EmailBroadcast::class), $roleName);
            self::assertTrue($user->can('create', EmailBroadcast::class), $roleName);
            self::assertTrue($user->can('update', $broadcast), $roleName);
            self::assertTrue($user->can('schedule', $broadcast), $roleName);
            self::assertTrue($user->can('delete', $broadcast), $roleName);
        }

        foreach (['member', 'recruitment-staff', 'recruitment-interviewer'] as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            self::assertFalse($user->can('viewAny', EmailBroadcast::class), $roleName);
            self::assertFalse($user->can('create', EmailBroadcast::class), $roleName);
            self::assertFalse($user->can('update', $broadcast), $roleName);
            self::assertFalse($user->can('schedule', $broadcast), $roleName);
        }
    }

    public function test_audit_p2_012_feature_flag_denies_every_action_even_for_seeded_admin(): void
    {
        config(['features.broadcast' => false]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $broadcast = new EmailBroadcast();

        self::assertFalse($admin->can('viewAny', EmailBroadcast::class));
        self::assertFalse($admin->can('create', EmailBroadcast::class));
        self::assertFalse($admin->can('update', $broadcast));
        self::assertFalse($admin->can('schedule', $broadcast));
        self::assertFalse($admin->can('cancel', $broadcast));
        self::assertFalse($admin->can('retry', $broadcast));
        self::assertFalse($admin->can('delete', $broadcast));
    }

    public function test_audit_p2_012_broadcast_policy_matches_create_and_schedule_roles(): void
    {
        $schedulerRole = Role::findOrCreate('audit-p2-012-scheduler', 'web');
        $schedulerRole->syncPermissions(['email-broadcast.schedule']);
        $scheduler = User::factory()->create();
        $scheduler->assignRole($schedulerRole);

        $creatorRole = Role::findOrCreate('audit-p2-012-creator', 'web');
        $creatorRole->syncPermissions(['email-broadcast.create']);
        $creator = User::factory()->create();
        $creator->assignRole($creatorRole);

        $broadcast = new EmailBroadcast();

        self::assertTrue($scheduler->can('schedule', $broadcast));
        self::assertFalse($scheduler->can('update', $broadcast));
        self::assertTrue($creator->can('update', $broadcast));
        self::assertFalse($creator->can('schedule', $broadcast));

        config(['features.broadcast' => false]);

        self::assertFalse($scheduler->can('schedule', $broadcast));
        self::assertFalse($scheduler->can('update', $broadcast));
        self::assertFalse($creator->can('update', $broadcast));
    }
}
