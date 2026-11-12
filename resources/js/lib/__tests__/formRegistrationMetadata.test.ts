import { describe, expect, it } from 'vitest';
import {
    emptyFormRegistrationMetadata,
    parseFormRegistrationMetadata,
    toFormMetadataPayload,
} from '@/lib/formRegistrationMetadata';

const DEFAULT_SECTIONS = [{ id: 'section-1', title: 'Bagian 1' }];

describe('emptyFormRegistrationMetadata', () => {
    it('starts as a plain registration form with one default section', () => {
        expect(emptyFormRegistrationMetadata()).toEqual({
            purpose: 'registration',
            requires_form_id: null,
            registration_mode: null,
            max_team_size: null,
            team_size: null,
            sections: DEFAULT_SECTIONS,
        });
    });
});

describe('parseFormRegistrationMetadata', () => {
    it('defaults unknown or missing purpose to registration', () => {
        expect(parseFormRegistrationMetadata({}).purpose).toBe('registration');
        expect(parseFormRegistrationMetadata({ purpose: 'weird' }).purpose).toBe('registration');
        expect(parseFormRegistrationMetadata({ purpose: 'other' }).purpose).toBe('other');
    });

    it('keeps a non-empty string form id and nulls everything else', () => {
        expect(parseFormRegistrationMetadata({ requires_form_id: 'form-9' }).requires_form_id).toBe('form-9');
        expect(parseFormRegistrationMetadata({ requires_form_id: '' }).requires_form_id).toBeNull();
        expect(parseFormRegistrationMetadata({ requires_form_id: 42 }).requires_form_id).toBeNull();
    });

    it('only accepts the whitelisted registration modes', () => {
        expect(parseFormRegistrationMetadata({ registration_mode: 'single' }).registration_mode).toBe('single');
        expect(parseFormRegistrationMetadata({ registration_mode: 'bundle' }).registration_mode).toBe('bundle');
        expect(parseFormRegistrationMetadata({ registration_mode: 'team' }).registration_mode).toBe('team');
        expect(parseFormRegistrationMetadata({ registration_mode: 'galaxy' }).registration_mode).toBeNull();
    });

    it('drops team and bundle modes for other-purpose forms', () => {
        expect(
            parseFormRegistrationMetadata({ purpose: 'other', registration_mode: 'team' }).registration_mode
        ).toBeNull();
        expect(
            parseFormRegistrationMetadata({ purpose: 'other', registration_mode: 'bundle' }).registration_mode
        ).toBeNull();
        expect(parseFormRegistrationMetadata({ purpose: 'other', registration_mode: 'single' }).registration_mode).toBe(
            'single'
        );
    });

    it('normalizes numeric sizes and treats blank/null as absent', () => {
        const parsed = parseFormRegistrationMetadata({ max_team_size: '4', team_size: '' });

        expect(parsed.max_team_size).toBe(4);
        expect(parsed.team_size).toBeNull();
        expect(parseFormRegistrationMetadata({ max_team_size: null, team_size: '2' })).toMatchObject({
            max_team_size: null,
            team_size: 2,
        });
    });

    it('nulls non-numeric and whitespace sizes instead of producing NaN', () => {
        const parsed = parseFormRegistrationMetadata({ max_team_size: 'abc', team_size: '   ' });

        expect(parsed.max_team_size).toBeNull();
        expect(parsed.team_size).toBeNull();
    });

    it('keeps zero and decimal sizes as finite numbers', () => {
        const parsed = parseFormRegistrationMetadata({ max_team_size: '0', team_size: '2.5' });

        expect(parsed.max_team_size).toBe(0);
        expect(parsed.team_size).toBe(2.5);
    });

    it('fills missing section ids and titles by position', () => {
        const parsed = parseFormRegistrationMetadata({
            sections: [
                { id: '', title: '  ' },
                { id: 'sec-x', title: '' },
            ],
        });

        expect(parsed.sections).toEqual([
            { id: 'section-1', title: 'Bagian 1' },
            { id: 'sec-x', title: 'Bagian 2' },
        ]);
    });

    it('falls back to the default section when the list is unusable', () => {
        expect(parseFormRegistrationMetadata({ sections: 'nope' }).sections).toEqual(DEFAULT_SECTIONS);
        expect(parseFormRegistrationMetadata({ sections: [] }).sections).toEqual(DEFAULT_SECTIONS);
        expect(parseFormRegistrationMetadata({ sections: [null, 3] }).sections).toEqual(DEFAULT_SECTIONS);
    });
});

describe('toFormMetadataPayload', () => {
    it('always includes every registration key', () => {
        expect(Object.keys(toFormMetadataPayload(emptyFormRegistrationMetadata())).sort()).toEqual([
            'max_team_size',
            'purpose',
            'registration_mode',
            'requires_form_id',
            'sections',
            'team_size',
        ]);
    });

    it('nulls team limits and non-single modes for other-purpose forms', () => {
        const payload = toFormMetadataPayload({
            purpose: 'other',
            requires_form_id: 'form-9',
            registration_mode: 'team',
            max_team_size: 5,
            team_size: 3,
            sections: DEFAULT_SECTIONS,
        });

        expect(payload).toEqual({
            purpose: 'other',
            requires_form_id: 'form-9',
            registration_mode: null,
            max_team_size: null,
            team_size: null,
            sections: DEFAULT_SECTIONS,
        });

        expect(
            toFormMetadataPayload({
                purpose: 'other',
                requires_form_id: null,
                registration_mode: 'single',
                max_team_size: null,
                team_size: null,
                sections: DEFAULT_SECTIONS,
            }).registration_mode
        ).toBe('single');
    });

    it('passes numeric sizes through and nulls non-numeric ones', () => {
        const payload = toFormMetadataPayload({
            purpose: 'registration',
            requires_form_id: null,
            registration_mode: 'team',
            max_team_size: 4,
            team_size: Number.NaN,
            sections: DEFAULT_SECTIONS,
        });

        expect(payload.registration_mode).toBe('team');
        expect(payload.max_team_size).toBe(4);
        expect(payload.team_size).toBeNull();
    });

    it('round-trips empty metadata through payload and parser', () => {
        const empty = emptyFormRegistrationMetadata();

        expect(parseFormRegistrationMetadata(toFormMetadataPayload(empty))).toEqual(empty);
    });

    it('round-trips populated team metadata with sizes and sections', () => {
        const teamMetadata = {
            purpose: 'registration' as const,
            requires_form_id: null,
            registration_mode: 'team' as const,
            max_team_size: 4,
            team_size: 2,
            sections: [
                { id: 'section-1', title: 'Data Ketua' },
                { id: 'section-2', title: 'Data Anggota' },
            ],
        };

        expect(parseFormRegistrationMetadata(toFormMetadataPayload(teamMetadata))).toEqual(teamMetadata);
    });

    it('is idempotent when a stored size is invalid', () => {
        const first = parseFormRegistrationMetadata({ max_team_size: 'abc', team_size: '' });
        const payload = toFormMetadataPayload(first);

        expect(payload.max_team_size).toBeNull();
        expect(payload.team_size).toBeNull();
        expect(parseFormRegistrationMetadata(payload)).toEqual(first);
        expect(toFormMetadataPayload(parseFormRegistrationMetadata(payload))).toEqual(payload);
    });
});
