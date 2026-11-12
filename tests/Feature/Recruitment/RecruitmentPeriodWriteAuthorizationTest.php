<?php

namespace Tests\Feature\Recruitment;

use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentPeriodService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L-1: kepemilikan periode tidak menggantikan permission tulis.
 *
 * `update` (dipakai `open`, `close`, `sendGroupLink`, `sendQr`) hanya lolos untuk
 * super-admin atau pemegang `recruitment.periods.edit`; creator yang permission-nya
 * dicabut harus 403 pada seluruh aksi tulis. `view` tetap memberi akses baca ke
 * periode sendiri.
 */
class RecruitmentPeriodWriteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const WA_URL = 'https://chat.whatsapp.com/AbCdEfGhIjKlMnOpQrSt';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_l1_owner_with_permission_can_write(): void
    {
        $owner = $this->staffWithEdit();
        $period = $this->periodOwnedBy($owner);

        self::assertTrue($owner->can('update', $period));

        $this->actingAs($owner)->post(route('dashboard.recruitment.periods.open', $period))->assertRedirect();
        $this->actingAs($owner)->post(route('dashboard.recruitment.periods.close', $period))->assertRedirect();
        $this->actingAs($owner)->post(route('dashboard.recruitment.periods.send-group-link', $period))->assertRedirect();
        $this->actingAs($owner)->post(route('dashboard.recruitment.periods.send-qr', $period))->assertRedirect();
    }

    public function test_l1_demoted_owner_gets_forbidden_on_every_write_action(): void
    {
        $demotedOwner = $this->staffWithoutEdit();
        $period = $this->periodOwnedBy($demotedOwner);

        // Baca periode sendiri tetap boleh; yang dicabut hanya aksi tulis.
        self::assertTrue($demotedOwner->can('view', $period));
        self::assertFalse($demotedOwner->can('update', $period));
        $this->actingAs($demotedOwner)
            ->get(route('dashboard.recruitment.periods.show', $period))
            ->assertOk();

        foreach ([
            'dashboard.recruitment.periods.open',
            'dashboard.recruitment.periods.close',
            'dashboard.recruitment.periods.send-group-link',
            'dashboard.recruitment.periods.send-qr',
        ] as $routeName) {
            $this->actingAs($demotedOwner)
                ->post(route($routeName, $period))
                ->assertForbidden();
        }
    }

    public function test_l1_non_owner_with_permission_is_allowed(): void
    {
        $editor = $this->staffWithEdit();
        $period = $this->periodOwnedBy(User::factory()->create());

        self::assertTrue($editor->can('update', $period));

        $this->actingAs($editor)->post(route('dashboard.recruitment.periods.close', $period))->assertRedirect();
    }

    public function test_l1_staff_and_interviewer_without_permission_are_forbidden(): void
    {
        $period = $this->periodOwnedBy(User::factory()->create());

        foreach (['recruitment-staff', 'recruitment-interviewer'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            self::assertFalse($user->can('recruitment.periods.edit'));
            self::assertFalse($user->can('update', $period));

            $this->actingAs($user)
                ->post(route('dashboard.recruitment.periods.close', $period))
                ->assertForbidden();
        }
    }

    public function test_l1_super_admin_is_allowed_without_period_permission_rows(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $period = $this->periodOwnedBy(User::factory()->create());

        // Super-admin lolos lewat role, bukan lewat ownership atau permission eksplisit.
        self::assertTrue($superAdmin->can('update', $period));

        $this->actingAs($superAdmin)
            ->post(route('dashboard.recruitment.periods.close', $period))
            ->assertRedirect();
    }

    public function test_l1_seeded_roles_follow_permission_not_ownership(): void
    {
        $period = $this->periodOwnedBy(User::factory()->create());

        foreach (Role::query()->get() as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $expected = $role->name === 'super-admin' || $role->hasPermissionTo('recruitment.periods.edit');

            self::assertSame(
                $expected,
                $user->can('update', $period),
                "Role {$role->name} salah mengevaluasi update periode.",
            );
        }

        // Admin seeded memang pemegang permission; role recruitment tidak.
        self::assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('recruitment.periods.edit'));
        self::assertFalse(Role::findByName('recruitment-staff', 'web')->hasPermissionTo('recruitment.periods.edit'));
        self::assertFalse(Role::findByName('recruitment-interviewer', 'web')->hasPermissionTo('recruitment.periods.edit'));
    }

    public function test_l1_can_edit_prop_no_longer_trusts_ownership_alone(): void
    {
        $demotedOwner = $this->staffWithoutEdit();
        $period = $this->periodOwnedBy($demotedOwner);

        $props = app(RecruitmentPeriodService::class)->toInertiaArray($period, $demotedOwner);

        self::assertFalse($props['can_edit']);
        self::assertFalse($props['can_delete']);
    }

    private function staffWithEdit(): User
    {
        $user = $this->staffWithoutEdit();
        $user->givePermissionTo('recruitment.periods.edit');

        return $user;
    }

    private function staffWithoutEdit(): User
    {
        $user = User::factory()->create();
        $user->assignRole('recruitment-staff');

        return $user;
    }

    private function periodOwnedBy(User $owner): RecruitmentPeriod
    {
        return RecruitmentPeriod::factory()->create([
            'created_by' => $owner->id,
            'whatsapp_group_url' => self::WA_URL,
        ]);
    }
}
