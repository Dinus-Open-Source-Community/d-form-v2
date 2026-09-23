<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_users_index(): void
    {
        $this->get(route('dashboard.users.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_member_cannot_view_users_index(): void
    {
        $member = User::factory()->create();
        $member->assignRole('member');

        $this->actingAs($member)
            ->get(route('dashboard.users.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_cannot_view_users_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.users.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_store_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('dashboard.users.store'), [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new-admin@example.com']);
    }

    public function test_super_admin_can_view_users_index(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard.users.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/Users/Index')
                ->has('users.data')
                ->has('roleOptions')
            );
    }

    public function test_super_admin_can_create_admin_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->post(route('dashboard.users.store'), [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
            ])
            ->assertRedirect(route('dashboard.users.index'));

        $created = User::query()->where('email', 'new-admin@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('admin'));
    }

    public function test_super_admin_can_create_member_and_recruitment_roles(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        foreach (['member', 'recruitment-staff', 'recruitment-interviewer'] as $role) {
            $email = "{$role}@example.com";

            $this->actingAs($superAdmin)
                ->post(route('dashboard.users.store'), [
                    'name' => "User {$role}",
                    'email' => $email,
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'role' => $role,
                ])
                ->assertRedirect(route('dashboard.users.index'));

            $created = User::query()->where('email', $email)->first();
            $this->assertNotNull($created);
            $this->assertTrue($created->hasRole($role));
        }
    }

    public function test_super_admin_cannot_assign_super_admin_role_on_store(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->post(route('dashboard.users.store'), [
                'name' => 'Another Super',
                'email' => 'another-super@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'super-admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'another-super@example.com']);
    }

    public function test_super_admin_can_update_user_without_changing_password(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $target = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => 'original-password',
        ]);
        $target->assignRole('member');
        $originalHash = $target->getRawOriginal('password');

        $this->actingAs($superAdmin)
            ->put(route('dashboard.users.update', $target), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'password' => '',
                'password_confirmation' => '',
                'role' => 'admin',
            ])
            ->assertRedirect(route('dashboard.users.index'));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('updated@example.com', $target->email);
        $this->assertTrue($target->hasRole('admin'));
        $this->assertFalse($target->hasRole('member'));
        $this->assertSame($originalHash, $target->getRawOriginal('password'));
    }

    public function test_super_admin_cannot_assign_super_admin_role_on_update(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $target = User::factory()->create();
        $target->assignRole('member');

        $this->actingAs($superAdmin)
            ->put(route('dashboard.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'super-admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($target->fresh()->hasRole('member'));
    }

    public function test_super_admin_can_soft_delete_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $target = User::factory()->create(['email' => 'delete-me@example.com']);
        $target->assignRole('member');

        $this->actingAs($superAdmin)
            ->delete(route('dashboard.users.destroy', $target))
            ->assertRedirect(route('dashboard.users.index'));

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->delete(route('dashboard.users.destroy', $superAdmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_super_admin_cannot_edit_or_delete_other_super_admin(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $otherSuper = User::factory()->create(['email' => 'other-super@example.com']);
        $otherSuper->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard.users.edit', $otherSuper))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->put(route('dashboard.users.update', $otherSuper), [
                'name' => 'Hacked',
                'email' => 'hacked@example.com',
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->delete(route('dashboard.users.destroy', $otherSuper))
            ->assertForbidden();

        $otherSuper->refresh();
        $this->assertSame('other-super@example.com', $otherSuper->email);
        $this->assertTrue($otherSuper->hasRole('super-admin'));
        $this->assertNull($otherSuper->deleted_at);
    }

    public function test_admin_cannot_view_user_detail(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();
        $target->assignRole('member');

        $this->actingAs($admin)
            ->get(route('dashboard.users.show', $target))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_user_detail_with_registrations(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $member = User::factory()->create([
            'name' => 'Detail Member',
            'email' => 'detail-member@example.com',
        ]);
        $member->assignRole('member');

        $event = \App\Models\Event::factory()->create([
            'title' => 'Workshop Detail',
            'status' => \App\Enums\EventStatus::Published,
            'created_by' => $superAdmin->id,
        ]);

        $form = \App\Models\Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Form Pendaftaran',
        ]);

        \App\Models\FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $member->id,
            'review_status' => \App\Enums\FormAnswerReviewStatus::Accepted,
            'registration_code' => 'REG-DETAIL-1',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard.users.show', $member))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/Users/Show')
                ->where('user.id', $member->id)
                ->where('user.email', 'detail-member@example.com')
                ->where('stats.events_joined', 1)
                ->where('stats.registrations_accepted', 1)
                ->has('registrations', 1)
                ->where('registrations.0.registration_code', 'REG-DETAIL-1')
                ->where('registrations.0.event.title', 'Workshop Detail')
                ->where('permissions.can_edit', true)
                ->where('permissions.can_delete', true)
            );
    }

    public function test_super_admin_can_view_other_super_admin_detail_read_only(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $otherSuper = User::factory()->create(['name' => 'Other Super']);
        $otherSuper->assignRole('super-admin');

        $this->actingAs($superAdmin)
            ->get(route('dashboard.users.show', $otherSuper))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                ->component('Dashboard/Users/Show')
                ->where('user.id', $otherSuper->id)
                ->where('permissions.can_edit', false)
                ->where('permissions.can_delete', false)
            );
    }
}
