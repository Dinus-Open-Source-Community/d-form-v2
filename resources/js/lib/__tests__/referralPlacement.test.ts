import { describe, expect, it } from 'vitest';
import { REFERRAL_POSITION_END, REFERRAL_POSITION_START } from '@/constants/referral';
import {
    normalizeReferralPosition,
    referralInsertIndexWithinSection,
    referralPositionForSectionIndex,
} from '@/lib/referralPlacement';

const ALL = [{ id: 'a' }, { id: 'b' }, { id: 'c' }, { id: 'd' }];
const FIRST_SECTION = [ALL[0], ALL[1]];
const SECOND_SECTION = [ALL[2], ALL[3]];

describe('normalizeReferralPosition', () => {
    it('mempertahankan sentinel dan id field', () => {
        expect(normalizeReferralPosition(REFERRAL_POSITION_START)).toBe(REFERRAL_POSITION_START);
        expect(normalizeReferralPosition(REFERRAL_POSITION_END)).toBe(REFERRAL_POSITION_END);
        expect(normalizeReferralPosition('b')).toBe('b');
    });

    it('jatuh ke akhir untuk nilai kosong atau bukan string', () => {
        expect(normalizeReferralPosition('')).toBe(REFERRAL_POSITION_END);
        expect(normalizeReferralPosition('   ')).toBe(REFERRAL_POSITION_END);
        expect(normalizeReferralPosition(null)).toBe(REFERRAL_POSITION_END);
        expect(normalizeReferralPosition(12)).toBe(REFERRAL_POSITION_END);
    });
});

describe('referralInsertIndexWithinSection', () => {
    it('start hanya tampil di bagian pertama', () => {
        expect(referralInsertIndexWithinSection(ALL, FIRST_SECTION, REFERRAL_POSITION_START)).toBe(0);
        expect(referralInsertIndexWithinSection(ALL, SECOND_SECTION, REFERRAL_POSITION_START)).toBeNull();
    });

    it('end hanya tampil di bagian terakhir', () => {
        expect(referralInsertIndexWithinSection(ALL, SECOND_SECTION, REFERRAL_POSITION_END)).toBe(2);
        expect(referralInsertIndexWithinSection(ALL, FIRST_SECTION, REFERRAL_POSITION_END)).toBeNull();
    });

    it('id field menempatkan blok tepat setelah field itu', () => {
        expect(referralInsertIndexWithinSection(ALL, FIRST_SECTION, 'a')).toBe(1);
        expect(referralInsertIndexWithinSection(ALL, SECOND_SECTION, 'd')).toBe(2);
        expect(referralInsertIndexWithinSection(ALL, FIRST_SECTION, 'd')).toBeNull();
    });

    it('anchor yang hilang kembali ke akhir bagian terakhir', () => {
        expect(referralInsertIndexWithinSection(ALL, SECOND_SECTION, 'deleted-field')).toBe(2);
        expect(referralInsertIndexWithinSection(ALL, FIRST_SECTION, 'deleted-field')).toBeNull();
    });

    it('bagian kosong tidak menampilkan blok', () => {
        expect(referralInsertIndexWithinSection(ALL, [], REFERRAL_POSITION_END)).toBeNull();
    });
});

describe('referralPositionForSectionIndex', () => {
    it('memakai field sebelumnya sebagai anchor', () => {
        expect(referralPositionForSectionIndex(ALL, FIRST_SECTION, 1)).toBe('a');
        expect(referralPositionForSectionIndex(ALL, SECOND_SECTION, 1)).toBe('c');
    });

    it('indeks nol memakai field global sebelumnya', () => {
        expect(referralPositionForSectionIndex(ALL, SECOND_SECTION, 0)).toBe('b');
        expect(referralPositionForSectionIndex(ALL, FIRST_SECTION, 0)).toBe(REFERRAL_POSITION_START);
    });

    it('indeks di akhir memakai field terakhir bagian', () => {
        expect(referralPositionForSectionIndex(ALL, FIRST_SECTION, 2)).toBe('b');
        expect(referralPositionForSectionIndex(ALL, SECOND_SECTION, 2)).toBe('d');
    });
});
