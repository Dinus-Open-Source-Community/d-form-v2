import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { runArtisanCommand } from './support/artisan';
import { readEnvironment } from './support/environment';

const ARCHIVE_EVENT_LABEL = 'Archive event';

/** Bungkus nilai sebagai string literal PHP single-quote yang aman. */
function phpStringLiteral(value: string): string {
    return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/** Pindahkan `events.created_by` fixture ke akun admin yang dipakai spec ini. */
function claimEventForAdmin(eventId: string): void {
    const adminEmail = readEnvironment().adminEmail;

    if (adminEmail === '') {
        throw new Error('E2E_ADMIN_EMAIL must be set; the fixture event owner is reassigned to that account.');
    }

    runArtisanCommand([
        'tinker',
        '--execute',
        `$user=\\App\\Models\\User::where('email', ${phpStringLiteral(adminEmail)})->firstOrFail(); ` +
            `\\App\\Models\\Event::whereKey(${phpStringLiteral(eventId)})->update(['created_by'=>$user->id]); ` +
            `echo json_encode(['adminId'=>$user->id]);`,
    ]);
}

/** Probe styles/hit-testing dialog yang sedang terbuka. */
interface IDialogProbe {
    bodyPointerEvents: string;
    contentPointerEvents: string;
    overlayPointerEvents: string;
    buttonPointerEvents: string;
    hitTestRole: string;
    hitTestTag: string;
    hitTestClass: string;
    hitTestIsConfirmButton: boolean;
}

async function probeConfirmButton(page: Page, confirmLabel: string): Promise<IDialogProbe> {
    const dialog = page.getByRole('alertdialog');

    await expect(dialog).toBeVisible();
    await page.waitForTimeout(300);

    return page.evaluate(
        ({ label }: { label: string }): IDialogProbe => {
            const dialogElement = document.querySelector('[role="alertdialog"]');
            const overlay = document.querySelector('[data-state="open"][class*="bg-black"]');
            const buttonElement = Array.from(document.querySelectorAll('[role="alertdialog"] button')).find(
                (candidate) => candidate.textContent?.trim() === label
            );

            if (dialogElement === null || buttonElement === undefined) {
                throw new Error(`probe: dialog/button for "${label}" not found`);
            }

            const rect = buttonElement.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const hit = document.elementFromPoint(cx, cy);

            return {
                bodyPointerEvents: getComputedStyle(document.body).pointerEvents,
                contentPointerEvents: getComputedStyle(dialogElement).pointerEvents,
                overlayPointerEvents: overlay === null ? 'none-found' : getComputedStyle(overlay).pointerEvents,
                buttonPointerEvents: getComputedStyle(buttonElement).pointerEvents,
                hitTestRole: hit?.getAttribute('role') ?? '',
                hitTestTag: hit?.tagName ?? 'null',
                hitTestClass: hit?.getAttribute('class') ?? '',
                hitTestIsConfirmButton: hit === buttonElement,
            };
        },
        { label: confirmLabel }
    );
}

test.describe('FE_AUDIT_016 confirmation dialog pointer events', () => {
    test.use({ fixtureFormCount: 1, fixtureFieldCount: 1 });

    test('FE_AUDIT_016 mouse click reaches confirm handler after animation', async ({ page, fixtureEvent }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await page.goto(`/admin/events/${fixtureEvent.eventId}`, { waitUntil: 'domcontentloaded' });

        await page.getByRole('button', { name: ARCHIVE_EVENT_LABEL }).click();

        const probe = await probeConfirmButton(page, 'Archive');

        console.log('FE_AUDIT_016 probe', JSON.stringify(probe, null, 2));

        expect(probe.buttonPointerEvents).toBe('auto');
        expect(probe.hitTestIsConfirmButton).toBe(true);

        await page.getByRole('alertdialog').getByRole('button', { name: 'Archive', exact: true }).click();
        await expect(page).toHaveURL(/\/admin\/events$/);
    });

    test('FE_AUDIT_016 Enter activates confirm and Escape dismisses dialog', async ({ page, fixtureEvent }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await page.goto(`/admin/events/${fixtureEvent.eventId}`, { waitUntil: 'domcontentloaded' });

        await page.getByRole('button', { name: ARCHIVE_EVENT_LABEL }).click();
        const dialog = page.getByRole('alertdialog');
        const confirmButton = dialog.getByRole('button', { name: 'Archive', exact: true });
        await expect(dialog).toBeVisible();
        await confirmButton.focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/admin\/events$/);

        await page.goto(`/admin/events/${fixtureEvent.eventId}`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Restore event', exact: true }).click();
        const restoreDialog = page.getByRole('alertdialog');
        await expect(restoreDialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(restoreDialog).toBeHidden();
        await expect(page).toHaveURL(new RegExp(`/admin/events/${fixtureEvent.eventId}$`));
    });

    test('FE_AUDIT_016 loading disables confirm and prevents double submit', async ({ page, fixtureEvent }) => {
        claimEventForAdmin(fixtureEvent.eventId);
        await page.goto(`/admin/events/${fixtureEvent.eventId}`, { waitUntil: 'domcontentloaded' });

        let deleteRequestCount = 0;
        let releaseRequest: (() => void) | undefined;
        const requestHeld = new Promise<void>((resolve) => {
            releaseRequest = resolve;
        });

        await page.route('**/admin/events/*', async (route) => {
            const request = route.request();
            if (request.method() !== 'DELETE' || !request.url().endsWith(`/admin/events/${fixtureEvent.eventId}`)) {
                await route.continue();
                return;
            }

            deleteRequestCount += 1;
            await requestHeld;
            await route.continue();
        });

        await page.getByRole('button', { name: ARCHIVE_EVENT_LABEL }).click();
        const dialog = page.getByRole('alertdialog');
        const confirmButton = dialog.getByRole('button', { name: 'Archive', exact: true });
        await confirmButton.click();
        await expect(confirmButton).toBeDisabled();
        await expect.poll(() => deleteRequestCount).toBe(1);

        const buttonBox = await confirmButton.boundingBox();
        expect(buttonBox).not.toBeNull();
        if (buttonBox !== null) {
            await page.mouse.click(buttonBox.x + buttonBox.width / 2, buttonBox.y + buttonBox.height / 2);
        }
        expect(deleteRequestCount).toBe(1);

        releaseRequest?.();
        await expect(page).toHaveURL(/\/admin\/events$/);
    });
});
