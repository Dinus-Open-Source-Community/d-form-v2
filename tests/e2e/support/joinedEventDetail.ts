import { expect, type Page } from '@playwright/test';
import { readEnvironment } from './environment';
import { runArtisanCommand } from './artisan';

export type TJoinedEventDetailStatus =
    | 'allowed'
    | 'not_visible'
    | 'form_closed'
    | 'registration_not_open'
    | 'quota_full'
    | 'unsupported_registration_mode'
    | 'pending_team_confirmation'
    | 'prerequisite_not_met';

export interface IJoinedEventDetailMember {
    userId: string;
    memberEmail: string;
    memberPassword: string;
    eventId: string;
    eventSlug: string;
    eventTitle: string;
}

export interface IJoinedEventDetailStatusCase extends IJoinedEventDetailMember {
    expectedStatus: TJoinedEventDetailStatus;
}

export interface IJoinedEventDetailFixture {
    cases: IJoinedEventDetailStatusCase[];
    /** Allowed case whose hero renders a stored banner image. */
    bannerCase: IJoinedEventDetailMember;
    /** Accepted registration that exposes the check-in card. */
    acceptedCase: IJoinedEventDetailMember;
    acceptedRegistrationCode: string;
    /** Public-disk path of the banner written for `bannerCase`. */
    bannerPath: string;
}

/** 1x1 opaque PNG; enough for the browser to report a decoded intrinsic width. */
const BANNER_PNG_BASE64 =
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

/**
 * Statuses the detail page can actually render.
 *
 * `invitation_closed` is absent: declining or expiring an invitation deletes the
 * member row so the member can register individually, asserted by
 * `FormRegistrationTest::test_member_can_register_individually_after_declining_invitation`.
 * `already_submitted` and `event_form_already_chosen` are absent from the inline
 * registration list because that list only renders for unregistered members; both
 * are covered at the access-guard level in `FormRegistrationTest`.
 */
const STATUS_CASES: readonly TJoinedEventDetailStatus[] = [
    'allowed',
    'not_visible',
    'form_closed',
    'registration_not_open',
    'quota_full',
    'unsupported_registration_mode',
    'pending_team_confirmation',
    'prerequisite_not_met',
];

/** Create isolated member events that expose every form access status in the detail UI. */
export function createJoinedEventDetailFixture(): IJoinedEventDetailFixture {
    const environment = readEnvironment();
    if (environment.adminEmail === '') {
        throw new Error('E2E_ADMIN_EMAIL must be set to create the joined detail fixture.');
    }

    const token = `e2e-joined-detail-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
    const commandParts: string[] = [
        `$owner=App\\Models\\User::where('email','${environment.adminEmail}')->firstOrFail();`,
        '$cases=[];',
    ];

    for (const status of STATUS_CASES) {
        const memberEmail = `${token}-${status}@example.test`;
        const memberPassword = `E2e${Math.random().toString(36).slice(2, 14)}Pass`;
        const eventSlug = `${token}-${status}`;
        const eventTitle = `${token} ${status}`;

        commandParts.push(
            `$member=App\\Models\\User::factory()->create(['name'=>'E2E Joined Detail ${status}','email'=>'${memberEmail}','password'=>\\Illuminate\\Support\\Facades\\Hash::make('${memberPassword}'),'email_verified_at'=>now()]);`,
            `$member->assignRole('member');`,
            `$event=App\\Models\\Event::factory()->create(['title'=>'${eventTitle}','slug'=>'${eventSlug}','description'=>'<p>E2E joined event detail</p>','status'=>'published','category'=>'etc','session'=>'general','created_by'=>$owner->id,'registration_start'=>now()->subDay(),'registration_end'=>now()->addDay(),'start_date'=>now()->addDay()->toDateString(),'end_date'=>now()->addDays(2)->toDateString(),'location'=>'E2E joined detail venue','quota'=>10,'registered_count'=>0,'banner'=>'']);`,
            `$form=$event->forms()->create(['title'=>'${eventTitle} form','description'=>'E2E inline registration form','visible_for'=>['public'],'closed_at'=>now()->addDay(),'metadata'=>['purpose'=>'registration','registration_mode'=>'single'],'status'=>'published']);`,
            `$form->formFields()->create(['label'=>'Name','name'=>'name','input_type'=>'input','metadata'=>['rules'=>['required'=>true]],'order'=>0]);`
        );

        if (status === 'not_visible') {
            commandParts.push(`$form->update(['visible_for'=>['admin']]);`);
        }
        if (status === 'form_closed') {
            commandParts.push(`$form->update(['closed_at'=>now()->subMinute()]);`);
        }
        if (status === 'registration_not_open') {
            commandParts.push(`$event->update(['registration_start'=>now()->addDay()]);`);
        }
        if (status === 'quota_full') {
            commandParts.push(`$event->update(['quota'=>1,'registered_count'=>1]);`);
        }
        if (status === 'unsupported_registration_mode') {
            commandParts.push(`$form->update(['metadata'=>['purpose'=>'registration','registration_mode'=>'pair']]);`);
        }
        if (status === 'pending_team_confirmation') {
            commandParts.push(
                `$answer=App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$member->id,'answers'=>[],'registration_role'=>'member','member_confirmation_status'=>'pending','invited_email'=>'${memberEmail}','invitation_token'=>'${token}-invite','review_status'=>'pending','is_active'=>1]);`
            );
        }
        if (status === 'prerequisite_not_met') {
            // Accepted registration makes the participant list visible; the required
            // form is never submitted, so the participant form stays blocked.
            commandParts.push(
                `$answer=App\\Models\\FormAnswer::create(['form_id'=>$form->id,'user_id'=>$member->id,'answers'=>[],'registration_role'=>'leader','review_status'=>'accepted','reviewed_at'=>now(),'is_active'=>1]);`,
                `$required=$event->forms()->create(['title'=>'${eventTitle} required','description'=>'Required form','visible_for'=>['public'],'closed_at'=>now()->addDay(),'metadata'=>['purpose'=>'registration','registration_mode'=>'single'],'status'=>'published']);`,
                `$required->formFields()->create(['label'=>'Name','name'=>'name','input_type'=>'input','metadata'=>['rules'=>['required'=>true]],'order'=>0]);`,
                `$other=$event->forms()->create(['title'=>'${eventTitle} participant','description'=>'Participant form','visible_for'=>['participant'],'closed_at'=>now()->addDay(),'metadata'=>['purpose'=>'other','requires_form_id'=>$required->id],'status'=>'published']);`,
                `$other->formFields()->create(['label'=>'Feedback','name'=>'feedback','input_type'=>'input','metadata'=>[],'order'=>0]);`
            );
        }

        commandParts.push(
            `$cases[]=['userId'=>$member->id,'memberEmail'=>'${memberEmail}','memberPassword'=>'${memberPassword}','eventId'=>$event->id,'eventSlug'=>$event->slug,'eventTitle'=>$event->title,'expectedStatus'=>'${status}'];`
        );
    }

    const bannerEmail = `${token}-banner@example.test`;
    const bannerPassword = `E2e${Math.random().toString(36).slice(2, 14)}Pass`;
    const bannerSlug = `${token}-banner`;
    const bannerTitle = `${token} banner`;
    const bannerPath = `events/banners/${token}-banner.png`;

    commandParts.push(
        `$bannerMember=App\\Models\\User::factory()->create(['name'=>'E2E Joined Detail banner','email'=>'${bannerEmail}','password'=>\\Illuminate\\Support\\Facades\\Hash::make('${bannerPassword}'),'email_verified_at'=>now()]);`,
        `$bannerMember->assignRole('member');`,
        `$bannerPath='${bannerPath}';`,
        `\\Illuminate\\Support\\Facades\\Storage::disk('public')->put($bannerPath, base64_decode('${BANNER_PNG_BASE64}'));`,
        `$bannerEvent=App\\Models\\Event::factory()->create(['title'=>'${bannerTitle}','slug'=>'${bannerSlug}','description'=>'<p>E2E banner event detail</p>','status'=>'published','category'=>'etc','session'=>'general','created_by'=>$owner->id,'registration_start'=>now()->subDay(),'registration_end'=>now()->addDay(),'start_date'=>now()->addDay()->toDateString(),'end_date'=>now()->addDays(2)->toDateString(),'location'=>'E2E banner venue','quota'=>10,'registered_count'=>0,'banner'=>$bannerPath]);`,
        `$bannerCase=['userId'=>$bannerMember->id,'memberEmail'=>'${bannerEmail}','memberPassword'=>'${bannerPassword}','eventId'=>$bannerEvent->id,'eventSlug'=>$bannerEvent->slug,'eventTitle'=>$bannerEvent->title];`
    );

    const acceptedEmail = `${token}-accepted@example.test`;
    const acceptedPassword = `E2e${Math.random().toString(36).slice(2, 14)}Pass`;
    const acceptedSlug = `${token}-accepted`;
    const acceptedTitle = `${token} accepted`;
    const acceptedFormTitle = `${acceptedTitle} form`;
    const acceptedRegistrationCode = `E2E-CHK-${Math.random().toString(36).slice(2, 10).toUpperCase()}`;

    commandParts.push(
        `$acceptedMember=App\\Models\\User::factory()->create(['name'=>'E2E Joined Detail accepted','email'=>'${acceptedEmail}','password'=>\\Illuminate\\Support\\Facades\\Hash::make('${acceptedPassword}'),'email_verified_at'=>now()]);`,
        `$acceptedMember->assignRole('member');`,
        `$acceptedEvent=App\\Models\\Event::factory()->create(['title'=>'${acceptedTitle}','slug'=>'${acceptedSlug}','description'=>'<p>E2E accepted event detail</p>','status'=>'published','category'=>'etc','session'=>'general','created_by'=>$owner->id,'registration_start'=>now()->subDay(),'registration_end'=>now()->addDay(),'start_date'=>now()->addDay()->toDateString(),'end_date'=>now()->addDays(2)->toDateString(),'location'=>'E2E accepted venue','quota'=>10,'registered_count'=>1,'banner'=>'']);`,
        `$acceptedForm=$acceptedEvent->forms()->create(['title'=>'${acceptedFormTitle}','description'=>'E2E accepted registration form','visible_for'=>['public'],'closed_at'=>now()->addDay(),'metadata'=>['purpose'=>'registration','registration_mode'=>'single'],'status'=>'published']);`,
        `$acceptedForm->formFields()->create(['label'=>'Name','name'=>'name','input_type'=>'input','metadata'=>['rules'=>['required'=>true]],'order'=>0]);`,
        `App\\Models\\FormAnswer::create(['form_id'=>$acceptedForm->id,'user_id'=>$acceptedMember->id,'answers'=>[],'registration_role'=>'leader','review_status'=>'accepted','reviewed_at'=>now(),'registration_code'=>'${acceptedRegistrationCode}','is_active'=>1]);`,
        `$acceptedCase=['userId'=>$acceptedMember->id,'memberEmail'=>'${acceptedEmail}','memberPassword'=>'${acceptedPassword}','eventId'=>$acceptedEvent->id,'eventSlug'=>$acceptedEvent->slug,'eventTitle'=>$acceptedEvent->title];`
    );

    commandParts.push(
        `echo json_encode(['cases'=>$cases,'bannerCase'=>$bannerCase,'acceptedCase'=>$acceptedCase,'acceptedRegistrationCode'=>'${acceptedRegistrationCode}','bannerPath'=>$bannerPath]);`
    );
    const fixtureOutput = runArtisanCommand(['tinker', '--execute', commandParts.join('')]);
    try {
        return JSON.parse(fixtureOutput) as IJoinedEventDetailFixture;
    } catch (error: unknown) {
        throw new Error(`Joined detail fixture output was not JSON: ${fixtureOutput.slice(0, 4000)}`, { cause: error });
    }
}

/** Remove only rows created by the joined detail fixture, plus the stored banner file. */
export function cleanupJoinedEventDetailFixture(fixture: IJoinedEventDetailFixture): void {
    const members: IJoinedEventDetailMember[] = [...fixture.cases, fixture.bannerCase, fixture.acceptedCase];
    const eventIds = members.map((item: IJoinedEventDetailMember): string => `'${item.eventId}'`).join(',');
    const userIds = members.map((item: IJoinedEventDetailMember): string => `'${item.userId}'`).join(',');
    runArtisanCommand([
        'tinker',
        '--execute',
        `\\Illuminate\\Support\\Facades\\Storage::disk('public')->delete('${fixture.bannerPath}');` +
            `App\\Models\\EventAttendance::query()->whereIn('form_answer_id',App\\Models\\FormAnswer::query()->whereIn('user_id',[${userIds}])->pluck('id'))->delete();` +
            `App\\Models\\FormAnswer::whereIn('user_id',[${userIds}])->delete();` +
            `$formIds=App\\Models\\Form::withTrashed()->whereIn('event_id',[${eventIds}])->pluck('id');` +
            `App\\Models\\FormField::withTrashed()->whereIn('form_id',$formIds)->get()->each->forceDelete();` +
            `App\\Models\\Form::withTrashed()->whereIn('event_id',[${eventIds}])->get()->each->forceDelete();` +
            `App\\Models\\Event::withTrashed()->whereIn('id',[${eventIds}])->get()->each->forceDelete();` +
            `App\\Models\\User::whereIn('id',[${userIds}])->delete();`,
    ]);
}

/**
 * Log in the fixture member through the product login page.
 *
 * Menunggu `#app[data-v-app]` memastikan Vue sudah hydrate: klik sebelum hydrate hanya
 * mengirim submit native dan halaman tetap di `/auth/login`, yang sebelumnya membuat
 * suite mobile flaky. Satu retry menutup sisa race setelah form terkirim.
 */

/** Tandai peserta accepted sudah dipindai agar state check-in berubah. */
export function markJoinedEventDetailCheckedIn(fixture: IJoinedEventDetailFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `$answer=App\\Models\\FormAnswer::query()->where('user_id','${fixture.acceptedCase.userId}')->firstOrFail();` +
            `App\\Models\\EventAttendance::query()->firstOrCreate(['form_answer_id'=>$answer->id],[` +
            `'event_id'=>$answer->form->event_id,` +
            `'scanned_by_user_id'=>$answer->user_id,` +
            `'scanned_at'=>now()->subMinutes(5)]);`,
    ]);
}

export async function signInJoinedEventDetailMember(page: Page, fixture: IJoinedEventDetailMember): Promise<void> {
    await page.goto('/auth/login');
    await page.locator('#app[data-v-app]').waitFor({ state: 'attached' });

    const emailField = page.locator('#login-email');
    await emailField.fill(fixture.memberEmail);
    await page.locator('#login-password').fill(fixture.memberPassword);

    const submitButton = page.locator('form').filter({ has: emailField }).getByRole('button', { name: /masuk/i });

    const leftLoginPage = async (): Promise<void> => {
        await page.waitForURL((url: URL): boolean => !url.pathname.startsWith('/auth/login'), { timeout: 15_000 });
    };

    await submitButton.click();

    try {
        await leftLoginPage();
    } catch {
        await submitButton.click();
        await leftLoginPage();
    }

    await expect(page).not.toHaveURL(/\/auth\/login/);
}
