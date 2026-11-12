import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { readEnvironment } from './environment';
import { runArtisanCommand } from './artisan';

const PNG_STUB = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP4z8DwHwAFAAH/VscvDQAAAABJRU5ErkJggg==';
const PDF_STUB = 'JVBERi0xLjcKMSAwIG9iago8PD4+CmVuZG9iagp0cmFpbGVyCjw8Pj4+CiUlRU9GCg==';
const IMAGE_FILE_NAME = 'e2e-joined-member-profile-photo-filename-that-demonstrates-ellipsis-on-narrow-screens.png';

export interface IJoinedMemberRegistrationFixture {
    userId: string;
    memberEmail: string;
    memberPassword: string;
    eventId: string;
    eventSlug: string;
    formId: string;
    answerId: string;
    imagePath: string;
    pdfPath: string;
}

/** Create an isolated accepted member registration and its private answer files. */
export function createJoinedMemberRegistrationFixture(): IJoinedMemberRegistrationFixture {
    const environment = readEnvironment();
    if (environment.adminEmail === '') {
        throw new Error('E2E_ADMIN_EMAIL must be set to create the joined member fixture.');
    }

    const token = `e2e-joined-member-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
    const memberEmail = `${token}@example.test`;
    const memberPassword = `E2e${Math.random().toString(36).slice(2, 14)}Pass`;
    const eventSlug = `${token}-event`;

    const command =
        `$owner=App\\Models\\User::where('email','${environment.adminEmail}')->firstOrFail();` +
        `$member=App\\Models\\User::factory()->create([` +
        `'name'=>'E2E Joined Member',` +
        `'email'=>'${memberEmail}',` +
        `'password'=>\\Illuminate\\Support\\Facades\\Hash::make('${memberPassword}'),` +
        `'email_verified_at'=>now()` +
        `]);` +
        `$member->assignRole('member');` +
        `$event=App\\Models\\Event::factory()->create([` +
        `'title'=>'${token}',` +
        `'slug'=>'${eventSlug}',` +
        `'description'=>'<p>E2E joined member registration</p>',` +
        `'status'=>'published',` +
        `'category'=>'etc',` +
        `'session'=>'general',` +
        `'created_by'=>$owner->id,` +
        `'registration_start'=>now()->subDay(),` +
        `'registration_end'=>now()->addDay(),` +
        `'start_date'=>now()->addDay()->toDateString(),` +
        `'end_date'=>now()->addDays(2)->toDateString(),` +
        `'location'=>'E2E private fixture venue',` +
        `'quota'=>10,` +
        `'registered_count'=>1,` +
        `'banner'=>''` +
        `]);` +
        `$form=$event->forms()->create([` +
        `'title'=>'${token} registration',` +
        `'description'=>'E2E joined member registration form',` +
        `'visible_for'=>['public'],` +
        `'closed_at'=>now()->addDay(),` +
        `'metadata'=>['purpose'=>'registration','registration_mode'=>'single'],` +
        `'status'=>'published'` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'Profile image',` +
        `'name'=>'profile_image',` +
        `'input_type'=>'fileUpload',` +
        `'metadata'=>['builderType'=>'image_upload','rules'=>['required'=>true,'mimes'=>'jpg,jpeg,png,webp','max_size'=>5120]],` +
        `'order'=>0` +
        `]);` +
        `$form->formFields()->create([` +
        `'label'=>'Supporting PDF',` +
        `'name'=>'supporting_pdf',` +
        `'input_type'=>'fileUpload',` +
        `'metadata'=>['builderType'=>'file_upload','rules'=>['required'=>true,'mimes'=>'pdf','max_size'=>5120]],` +
        `'order'=>1` +
        `]);` +
        `$imagePath='form-uploads/'.$form->id.'/${IMAGE_FILE_NAME}';` +
        `$pdfPath='form-uploads/'.$form->id.'/member-document.pdf';` +
        `\\Illuminate\\Support\\Facades\\Storage::disk('local')->put($imagePath,base64_decode('${PNG_STUB}'));` +
        `\\Illuminate\\Support\\Facades\\Storage::disk('local')->put($pdfPath,base64_decode('${PDF_STUB}'));` +
        `$answer=App\\Models\\FormAnswer::create([` +
        `'form_id'=>$form->id,` +
        `'user_id'=>$member->id,` +
        `'answers'=>['profile_image'=>$imagePath,'supporting_pdf'=>$pdfPath],` +
        `'review_status'=>'accepted',` +
        `'reviewed_at'=>now(),` +
        `'reviewed_by'=>$owner->id,` +
        `'registration_code'=>'E2E-'.strtoupper(substr(md5('${token}'),0,8)),` +
        `'is_active'=>1` +
        `]);` +
        `echo json_encode([` +
        `'userId'=>$member->id,` +
        `'memberEmail'=>'${memberEmail}',` +
        `'memberPassword'=>'${memberPassword}',` +
        `'eventId'=>$event->id,` +
        `'eventSlug'=>$event->slug,` +
        `'formId'=>$form->id,` +
        `'answerId'=>$answer->id,` +
        `'imagePath'=>$imagePath,` +
        `'pdfPath'=>$pdfPath` +
        `]);`;

    const output = runArtisanCommand(['tinker', '--execute', command]);

    try {
        return JSON.parse(output) as IJoinedMemberRegistrationFixture;
    } catch (error: unknown) {
        throw new Error(`Joined member fixture did not return JSON: ${output}`, { cause: error });
    }
}

/** Remove only the fixture rows and private files created by this spec. */
export function cleanupJoinedMemberRegistrationFixture(fixture: IJoinedMemberRegistrationFixture): void {
    runArtisanCommand([
        'tinker',
        '--execute',
        `App\\Models\\FormAnswer::whereKey('${fixture.answerId}')->delete();` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->delete(['${fixture.imagePath}','${fixture.pdfPath}']);` +
            `\\Illuminate\\Support\\Facades\\Storage::disk('local')->deleteDirectory('form-uploads/${fixture.formId}');` +
            `$form=App\\Models\\Form::withTrashed()->find('${fixture.formId}');` +
            `if($form){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$form->id)->get() as $field){$field->forceDelete();}` +
            `$form->forceDelete();` +
            `}` +
            `$event=App\\Models\\Event::withTrashed()->find('${fixture.eventId}');` +
            `if($event){` +
            `foreach(App\\Models\\Form::withTrashed()->where('event_id',$event->id)->get() as $eventForm){` +
            `foreach(App\\Models\\FormField::withTrashed()->where('form_id',$eventForm->id)->get() as $field){$field->forceDelete();}` +
            `$eventForm->forceDelete();` +
            `}` +
            `$event->forceDelete();` +
            `}` +
            `App\\Models\\User::whereKey('${fixture.userId}')->delete();`,
    ]);
}

/** Log in through the same browser form used by participant E2E coverage. */
export async function signInJoinedMember(page: Page, fixture: IJoinedMemberRegistrationFixture): Promise<void> {
    await page.goto('/auth/login');
    await page.locator('#login-email').fill(fixture.memberEmail);
    await page.locator('#login-password').fill(fixture.memberPassword);
    await page
        .locator('form')
        .filter({ has: page.locator('#login-email') })
        .getByRole('button', { name: /masuk/i })
        .click();
    await expect(page).not.toHaveURL(/\/auth\/login/);
}

/** Check the joined registration surface and its authenticated file routes. */
export async function expectJoinedMemberRegistrationSurface(
    page: Page,
    fixture: IJoinedMemberRegistrationFixture
): Promise<void> {
    await expect(page).toHaveURL(`/joined/${fixture.eventSlug}/registration`);

    const banner = page.getByTestId('event-registration-banner');
    await expect(banner).toHaveCount(0);
    expect(await page.locator('main').innerText()).not.toContain('No banner');

    const main = page.locator('main');
    expect(await main.innerText()).not.toContain('/form-answers/');
    await expect(page.getByText('Buka', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Unduh', { exact: true }).first()).toBeVisible();

    const imageRoute = `/form-answers/${fixture.answerId}/files/profile_image/${IMAGE_FILE_NAME}`;
    const image = page.locator('img[src*="/form-answers/"]').first();
    await expect(image).toBeVisible();
    expect(await image.evaluate((element) => new URL(element.getAttribute('src') ?? '', location.href).pathname)).toBe(
        imageRoute
    );
    await expect
        .poll(() => image.evaluate((element) => (element instanceof HTMLImageElement ? element.naturalWidth : 0)))
        .toBeGreaterThan(0);

    const imageCard = page.getByTestId('form-answer-image-card').first();
    const imageFilename = imageCard.getByTestId('form-answer-image-filename');
    await expect(imageFilename).toHaveAttribute('title', IMAGE_FILE_NAME);
    await expect(imageFilename).toHaveCSS('text-overflow', 'ellipsis');
    await expect.poll(() => imageFilename.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
    await expect(main.getByText('Layar penuh', { exact: true })).toHaveCount(0);

    await page.getByRole('button', { name: 'Buka gambar layar penuh' }).click();
    const lightbox = page.getByRole('dialog', { name: new RegExp(IMAGE_FILE_NAME) });
    await expect(lightbox).toBeVisible();
    await lightbox.getByRole('button', { name: 'Tutup pratinjau' }).click();
    await expect(lightbox).toHaveCount(0);

    const popupPromise = page.waitForEvent('popup');
    await page.getByText('Buka', { exact: true }).first().click();
    const openedFile = await popupPromise;
    await openedFile.waitForLoadState('domcontentloaded');
    expect(new URL(openedFile.url()).pathname).toBe(imageRoute);
    await openedFile.close();

    const pdfRoute = `/form-answers/${fixture.answerId}/files/supporting_pdf/member-document.pdf`;
    const pdfCard = page.getByTestId('form-answer-pdf-card').first();
    await expect(pdfCard).toBeVisible();
    const pdfFrame = pdfCard.locator('iframe');
    await expect(pdfFrame).toHaveAttribute('src', new RegExp(pdfRoute.replaceAll('/', '\\/') + '$'));

    await expect(page.getByText(/Accepted|Diterima/, { exact: true })).toBeVisible();
    await expect(page.getByText('Check-in', { exact: true })).toBeVisible();
    const qr = page.getByAltText('Attendance QR code');
    await expect(qr).toBeVisible();
    await expect
        .poll(() => qr.evaluate((element) => (element instanceof HTMLImageElement ? element.naturalWidth : 0)))
        .toBeGreaterThan(0);

    const metrics = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth);
}

/** Ensure the explicit screenshot destination exists before the run captures evidence. */
export async function prepareJoinedMemberScreenshotDirectory(): Promise<void> {
    await mkdir('/tmp/opencode', { recursive: true });
}
