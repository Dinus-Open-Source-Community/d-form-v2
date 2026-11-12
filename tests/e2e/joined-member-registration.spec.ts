import { expect, test } from '@playwright/test';
import {
    cleanupJoinedMemberRegistrationFixture,
    createJoinedMemberRegistrationFixture,
    expectJoinedMemberRegistrationSurface,
    prepareJoinedMemberScreenshotDirectory,
    signInJoinedMember,
    type IJoinedMemberRegistrationFixture,
} from './support/joinedMemberRegistration';

test.describe('joined member registration', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    let fixture: IJoinedMemberRegistrationFixture | null = null;

    test.beforeEach(() => {
        fixture = createJoinedMemberRegistrationFixture();
    });

    test.afterEach(() => {
        if (fixture !== null) {
            cleanupJoinedMemberRegistrationFixture(fixture);
            fixture = null;
        }
    });

    test('renders the private member registration without overflow', async ({ page }, testInfo) => {
        const currentFixture = fixture;
        expect(currentFixture).not.toBeNull();
        if (currentFixture === null) {
            return;
        }

        await prepareJoinedMemberScreenshotDirectory();
        await signInJoinedMember(page, currentFixture);

        const widths = testInfo.project.name === 'chromium-mobile' ? [375, 390] : [1440];
        for (const width of widths) {
            await page.setViewportSize({ width, height: width === 1440 ? 900 : 844 });
            await page.goto(`/joined/${currentFixture.eventSlug}/registration`);
            await expect(page).toHaveURL(`/joined/${currentFixture.eventSlug}/registration`);

            const screenshotStem = `joined-member-registration-${testInfo.project.name}-${width}px`;
            await page.screenshot({ path: `/tmp/opencode/${screenshotStem}-before.png`, fullPage: true });
            await expectJoinedMemberRegistrationSurface(page, currentFixture);
            await page.screenshot({ path: `/tmp/opencode/${screenshotStem}-after.png`, fullPage: true });
        }
    });
});
