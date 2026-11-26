import { describe, expect, it } from 'vitest';
import { buildBreadcrumbs, parentBreadcrumbHref, type IBreadcrumbItem } from '@/lib/breadcrumbs';

const EVENT_ID = '01a1c676-802b-719b-853c-8626c201962b';
const FORM_ID = '01a1c676-802b-719b-853c-8626c201963b';
const PERIOD_ID = '01a1c676-802b-719b-853c-8626c201964b';

/** Ringkas crumb menjadi `label|href` agar ekspektasi terbaca satu baris. */
function shape(items: IBreadcrumbItem[]): string[] {
    return items.map((item) => `${item.label}|${item.href === null ? 'STATIC' : item.href}`);
}

describe('buildBreadcrumbs', () => {
    it('menandai segmen formulir sebagai teks statis pada halaman edit form', () => {
        expect(shape(buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/edit`, 'Form Pendaftaran'))).toEqual([
            'Acara|/admin/events',
            `${EVENT_ID}|/admin/events/${EVENT_ID}`,
            'Formulir|STATIC',
            'Ubah|/admin/events/' + EVENT_ID + '/forms/edit',
        ]);
    });

    it('menandai segmen formulir sebagai teks statis pada halaman create form', () => {
        const items = buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/create`, 'Buat form');

        expect(shape(items)).toEqual([
            'Acara|/admin/events',
            `${EVENT_ID}|/admin/events/${EVENT_ID}`,
            'Formulir|STATIC',
            'Buat|/admin/events/' + EVENT_ID + '/forms/create',
        ]);
        // Hanya segmen Formulir yang statis; crumb lain tetap link.
        expect(items.filter((item) => item.href === null)).toHaveLength(1);
    });

    it('halaman edit form memakai judul halaman sebagai crumb terakhir saat segmen bukan aksi', () => {
        const items = buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/${EVENT_ID}`, 'Form Draf Internal');

        expect(shape(items)).toEqual([
            'Acara|/admin/events',
            `${EVENT_ID}|/admin/events/${EVENT_ID}`,
            'Formulir|STATIC',
            `Form Draf Internal|/admin/events/${EVENT_ID}/forms/${EVENT_ID}`,
        ]);
    });

    it('path non-formulir tetap hanya berisi link', () => {
        const items = buildBreadcrumbs(`/admin/events/${EVENT_ID}/edit`, 'Ubah');

        expect(shape(items)).toEqual([
            'Acara|/admin/events',
            `${EVENT_ID}|/admin/events/${EVENT_ID}`,
            `Ubah|/admin/events/${EVENT_ID}/edit`,
        ]);
        expect(items.every((item) => item.href !== null)).toBe(true);
    });

    it('base page hanya satu crumb', () => {
        expect(shape(buildBreadcrumbs('/admin/events', 'Acara'))).toEqual(['Acara|/admin/events']);
    });

    it('mengabaikan query string saat menyusun crumb', () => {
        expect(shape(buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/edit?tab=settings`, 'Form A'))).toEqual(
            shape(buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/edit`, 'Form A'))
        );
    });

    it('memakai nama entitas dari halaman untuk segmen id, bukan UUID mentah', () => {
        expect(
            shape(
                buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/${FORM_ID}`, 'Feedback Survey', {
                    segmentLabels: { [EVENT_ID]: 'Startup Pitch Night', [FORM_ID]: 'Feedback Survey' },
                })
            )
        ).toEqual([
            'Acara|/admin/events',
            `Startup Pitch Night|/admin/events/${EVENT_ID}`,
            'Formulir|STATIC',
            `Feedback Survey|/admin/events/${EVENT_ID}/forms/${FORM_ID}`,
        ]);
    });

    it('memakai label aksi Indonesia untuk segmen recruitment', () => {
        expect(shape(buildBreadcrumbs(`/admin/recruitment/periods/${PERIOD_ID}`, 'Scan Test OpRec 2026'))).toEqual([
            'Rekrutmen|/admin/recruitment',
            'Periode|/admin/recruitment/periods',
            `Scan Test OpRec 2026|/admin/recruitment/periods/${PERIOD_ID}`,
        ]);
        expect(shape(buildBreadcrumbs('/admin/recruitment/my-interviews', 'Interview Saya'))).toEqual([
            'Rekrutmen|/admin/recruitment',
            'Interview Saya|/admin/recruitment/my-interviews',
        ]);
    });

    it('profil memakai halaman utama sebagai induk', () => {
        const items = buildBreadcrumbs('/profile', 'Profil', { homeHref: '/joined' });

        expect(shape(items)).toEqual(['Beranda|/joined', 'Profil|/profile']);
        expect(parentBreadcrumbHref(items)).toBe('/joined');
    });
});

describe('parentBreadcrumbHref', () => {
    it('melewati crumb statis dan memakai link induk terdekat', () => {
        expect(parentBreadcrumbHref(buildBreadcrumbs(`/admin/events/${EVENT_ID}/forms/create`, 'Buat form'))).toBe(
            `/admin/events/${EVENT_ID}`
        );
    });

    it('null pada halaman akar', () => {
        expect(parentBreadcrumbHref(buildBreadcrumbs('/admin/events', 'Acara'))).toBeNull();
    });
});
