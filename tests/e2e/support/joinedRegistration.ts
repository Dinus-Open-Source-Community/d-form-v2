import { expect, type Page } from '@playwright/test';
import { readPageProps } from './network';

export interface IJoinedRegistrationData {
    event: {
        banner_url: string | null;
    };
    registration: {
        answers_summary: Record<string, string>;
    };
}

/** Read the server-rendered Inertia props so assertions compare UI URLs to their source payload. */
export async function readJoinedRegistrationData(page: Page): Promise<IJoinedRegistrationData> {
    const props = await readPageProps<unknown>(page);
    const data = extractJoinedRegistrationData({ props });
    if (data === null) {
        throw new Error('Joined registration Inertia payload is missing event or answer data.');
    }

    return data;
}

/** Require the rendered banner to use the exact server URL and remain within its compact frame. */
export async function expectJoinedBanner(page: Page): Promise<void> {
    const data = await readJoinedRegistrationData(page);
    expect(data.event.banner_url).not.toBeNull();

    const banner = page.getByTestId('event-registration-banner');
    await expect(banner).toBeVisible();
    await expect(banner.getByText('No banner', { exact: true })).toHaveCount(0);

    const image = banner.locator('img');
    await expect(image).toBeVisible();
    const expectedUrl = new URL(data.event.banner_url ?? '', page.url()).href;
    expect(await image.evaluate((element) => (element instanceof HTMLImageElement ? element.src : ''))).toBe(
        expectedUrl
    );
    await expect
        .poll(() => image.evaluate((element) => (element instanceof HTMLImageElement ? element.naturalWidth : 0)))
        .toBeGreaterThan(0);

    const response = await page.request.get(expectedUrl);
    expect(response.status()).toBe(200);

    const bounds = await banner.boundingBox();
    expect(bounds).not.toBeNull();
    expect(bounds?.height ?? 0).toBeGreaterThan(0);
    expect(bounds?.height ?? 0).toBeLessThanOrEqual(320);
}

/** Ensure private answer paths are represented only as authorized links, not visible text. */
export async function expectJoinedAnswerFiles(page: Page): Promise<void> {
    const data = await readJoinedRegistrationData(page);
    const privateFileUrls = Object.values(data.registration.answers_summary).filter(isPrivateAnswerFileUrl);
    expect(privateFileUrls.length).toBeGreaterThan(0);

    const main = page.locator('main');
    const visibleText = await main.innerText();
    expect(visibleText).not.toContain('/form-answers/');

    const openLinks = await main.getByRole('link', { name: 'Buka', exact: true }).all();
    const renderedHrefs = await Promise.all(openLinks.map((link) => link.getAttribute('href')));
    const downloadLinks = await main.getByRole('link', { name: 'Unduh', exact: true }).all();
    const downloadHrefs = await Promise.all(downloadLinks.map((link) => link.getAttribute('href')));
    for (const privateFileUrl of privateFileUrls) {
        expect(renderedHrefs).toContain(privateFileUrl);
        expect(downloadHrefs).toContain(privateFileUrl);
    }
    await expect(main.getByRole('link', { name: 'Unduh', exact: true }).first()).toBeVisible();
}

/** Keep image metadata and every toolbar action inside the card at narrow and desktop widths. */
export async function expectJoinedImageToolbarLayout(page: Page): Promise<void> {
    const card = page.getByTestId('form-answer-image-card').first();
    const filename = card.getByTestId('form-answer-image-filename');
    const cardBounds = await card.boundingBox();
    const filenameBounds = await filename.boundingBox();
    const viewportWidth = await page.evaluate(() => document.documentElement.clientWidth);

    await expect(card).toBeVisible();
    await expect(filename).toHaveAttribute('title', /.+/);
    await expect(filename).toHaveCSS('white-space', 'nowrap');
    await expect(filename).toHaveCSS('text-overflow', 'ellipsis');
    await expect.poll(() => filename.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
    await expect(card.getByText('Layar penuh', { exact: true })).toHaveCount(0);
    expect(cardBounds).not.toBeNull();
    expect(filenameBounds).not.toBeNull();
    expect((filenameBounds?.x ?? 0) + (filenameBounds?.width ?? 0)).toBeLessThanOrEqual(
        (cardBounds?.x ?? 0) + (cardBounds?.width ?? 0) + 1
    );

    for (const name of ['Buka', 'Unduh']) {
        const action = card.getByRole('link', { name, exact: true });
        await expect(action).toBeVisible();
        const bounds = await action.boundingBox();
        expect(bounds).not.toBeNull();
        expect((bounds?.x ?? 0) + (bounds?.width ?? 0)).toBeLessThanOrEqual(viewportWidth + 1);
    }
}

/** Confirm every rendered private image loaded, and verify the fallback when its request fails. */
export async function expectJoinedImageFallback(page: Page): Promise<void> {
    const answerImages = page.locator('main img[src*="/form-answers/"]');
    const imageCount = await answerImages.count();
    expect(imageCount).toBeGreaterThan(0);

    for (let index = 0; index < imageCount; index += 1) {
        const image = answerImages.nth(index);
        await expect(image).toBeVisible();
        await expect
            .poll(() => image.evaluate((element) => (element instanceof HTMLImageElement ? element.naturalWidth : 0)))
            .toBeGreaterThan(0);
    }

    await page.route('**/form-answers/**/files/**', (route) => route.abort());
    await page.reload();
    await expect(page.getByText('Pratinjau gambar tidak tersedia.', { exact: true })).toBeVisible();
}

/** Check viewport sizing and ensure no page-level horizontal scroll is introduced. */
export async function expectJoinedNoHorizontalOverflow(page: Page): Promise<void> {
    const metrics = await page.evaluate(() => ({
        documentWidth: document.documentElement.scrollWidth,
        viewportWidth: document.documentElement.clientWidth,
    }));
    expect(metrics.documentWidth).toBeLessThanOrEqual(metrics.viewportWidth);
}

function isPrivateAnswerFileUrl(value: string): boolean {
    return /^\/form-answers\/[^/]+\/files\/[^/]+\/.+$/.test(value);
}

function extractJoinedRegistrationData(value: unknown): IJoinedRegistrationData | null {
    if (typeof value !== 'object' || value === null || !('props' in value)) {
        return null;
    }

    const props = value.props;
    if (typeof props !== 'object' || props === null || !('event' in props) || !('registration' in props)) {
        return null;
    }

    const event = props.event;
    if (
        typeof event !== 'object' ||
        event === null ||
        !('banner_url' in event) ||
        (event.banner_url !== null && typeof event.banner_url !== 'string')
    ) {
        return null;
    }

    const registration = props.registration;
    if (
        typeof registration !== 'object' ||
        registration === null ||
        !('answers_summary' in registration) ||
        !isStringRecord(registration.answers_summary)
    ) {
        return null;
    }

    return {
        event: { banner_url: event.banner_url },
        registration: { answers_summary: registration.answers_summary },
    };
}

function isStringRecord(value: unknown): value is Record<string, string> {
    if (typeof value !== 'object' || value === null || Array.isArray(value)) {
        return false;
    }

    return Object.values(value).every((answer) => typeof answer === 'string');
}
