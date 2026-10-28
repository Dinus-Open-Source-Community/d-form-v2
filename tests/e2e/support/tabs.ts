import type { BrowserContext, Page } from '@playwright/test';

/**
 * Buka tab kedua dalam context yang sama sehingga cookie dan storage-nya identik.
 * Dipakai skenario dua tab tanpa membuat sesi/login baru.
 */
export async function openSecondTab(context: BrowserContext, path: string = '/'): Promise<Page> {
    const page = await context.newPage();
    await page.goto(path, { waitUntil: 'domcontentloaded' });

    return page;
}
