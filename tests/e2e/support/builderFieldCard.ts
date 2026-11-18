import type { Locator, Page } from '@playwright/test';

/** Kartu field di kanvas builder: pratinjau read-only dengan satu trigger drawer. */
export const FIELD_CARD = '[data-testid="field-card"]';

export function fieldCards(page: Page): Locator {
    return page.locator(FIELD_CARD);
}

/** Trigger "Ubah field ..." satu per kartu, urut sesuai kanvas. */
export function fieldCardTriggers(page: Page): Locator {
    return page.getByRole('button', { name: /^Ubah field/ });
}

/** Buka drawer editor field ke-`index` lalu kembalikan locator dialognya. */
export async function openFieldDrawer(page: Page, index: number): Promise<Locator> {
    await fieldCardTriggers(page).nth(index).click();

    const sheet = page.getByRole('dialog');

    await sheet.waitFor({ state: 'visible' });

    return sheet;
}

/** Tutup drawer lewat tombol Selesai. */
export async function closeFieldDrawer(page: Page): Promise<void> {
    await page.getByRole('dialog').getByRole('button', { name: 'Selesai' }).click();
}

/**
 * Rename field lewat drawer, satu-satunya jalur edit sejak kartu menjadi read-only.
 * Nilai baru langsung masuk ke state builder sehingga autosave berjalan seperti edit lain.
 */
export async function renameFieldViaDrawer(page: Page, index: number, label: string): Promise<void> {
    const sheet = await openFieldDrawer(page, index);

    await sheet.getByLabel('Label', { exact: true }).fill(label);

    await closeFieldDrawer(page);
}
