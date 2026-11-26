import type { TFormPurpose, IFormRegistrationMetadata, TFormMetadataPayload } from '@/types/form';
import type { IFormBuilderSection } from '@/types/form-builder';
import { isRecord } from '@/lib/isRecord';
import { normalizeReferralPosition } from '@/lib/referralPlacement';
import { REFERRAL_POSITION_END } from '@/constants/referral';

/** Stable fallback for legacy forms that predate persisted section metadata. */
export const DEFAULT_FORM_SECTION_ID = 'section-1';

/** Creates the minimum valid section list for a form builder. */
export function defaultFormBuilderSections(): IFormBuilderSection[] {
    return [{ id: DEFAULT_FORM_SECTION_ID, title: 'Bagian 1' }];
}

function parseBuilderSections(raw: unknown): IFormBuilderSection[] {
    if (!Array.isArray(raw)) return defaultFormBuilderSections();
    const sections = raw.flatMap((value, index): IFormBuilderSection[] => {
        if (!isRecord(value)) return [];
        const id = typeof value.id === 'string' && value.id.trim() !== '' ? value.id : `section-${index + 1}`;
        const title =
            typeof value.title === 'string' && value.title.trim() !== '' ? value.title : `Bagian ${index + 1}`;
        return [{ id, title }];
    });
    return sections.length > 0 ? sections : defaultFormBuilderSections();
}

/** Default registration metadata for a newly created dashboard form. */
export function emptyFormRegistrationMetadata(): IFormRegistrationMetadata {
    return {
        purpose: 'registration',
        requires_form_id: null,
        registration_mode: null,
        max_team_size: null,
        team_size: null,
        allow_multiple_submissions: false,
        allow_guest_submissions: false,
        max_submissions: 1,
        sections: defaultFormBuilderSections(),
        referral_position: REFERRAL_POSITION_END,
        meeting_schedule_enabled: false,
    };
}

/**
 * Baca ukuran tim dari metadata tersimpan. Nilai non-numerik atau whitespace
 * menjadi `null` supaya hasil baca dan payload tulis tetap simetris.
 */
function parseTeamSize(value: unknown): number | null {
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    if (typeof value !== 'string') return null;

    const trimmed: string = value.trim();
    if (trimmed === '') return null;

    const parsed: number = Number(trimmed);
    return Number.isFinite(parsed) ? parsed : null;
}

/** Normalizes any raw metadata bag into `IFormRegistrationMetadata`; used when loading an existing form. */
export function parseFormRegistrationMetadata(raw: unknown): IFormRegistrationMetadata {
    const m = isRecord(raw) ? raw : {};
    const purposeRaw = m['purpose'];
    const purpose: TFormPurpose =
        purposeRaw === 'registration' ||
        purposeRaw === 'answer_collection' ||
        purposeRaw === 'feedback' ||
        purposeRaw === 'd_kelas' ||
        purposeRaw === 'other'
            ? purposeRaw
            : 'registration';
    const requiresRaw = m['requires_form_id'];
    const requires_form_id = typeof requiresRaw === 'string' && requiresRaw !== '' ? requiresRaw : null;
    const mode = m['registration_mode'];
    const rm = mode === 'single' || mode === 'bundle' || mode === 'team' ? mode : null;
    const maxTs = m['max_team_size'];
    const teamS = m['team_size'];
    const allowMultiple =
        purpose !== 'registration' && purpose !== 'd_kelas' && m['allow_multiple_submissions'] === true;
    const allowGuestSubmissions =
        purpose !== 'registration' && purpose !== 'd_kelas' && m['allow_guest_submissions'] === true;
    const parsedMaxSubmissions = parseTeamSize(m['max_submissions']);
    const maxSubmissions =
        allowMultiple &&
        parsedMaxSubmissions !== null &&
        Number.isInteger(parsedMaxSubmissions) &&
        parsedMaxSubmissions >= 2
            ? parsedMaxSubmissions
            : allowMultiple
              ? 2
              : 1;
    return {
        purpose,
        requires_form_id,
        registration_mode: purpose === 'other' ? (rm === 'team' || rm === 'bundle' ? null : rm) : rm,
        max_team_size: parseTeamSize(maxTs),
        team_size: parseTeamSize(teamS),
        allow_multiple_submissions: allowMultiple,
        allow_guest_submissions: allowGuestSubmissions,
        max_submissions: maxSubmissions,
        sections: parseBuilderSections(m['sections']),
        referral_position: normalizeReferralPosition(m['referral_position']),
        meeting_schedule_enabled: m['meeting_schedule_enabled'] === true,
    };
}

/**
 * Builds the create/update metadata payload, always including every registration key so
 * Inertia null omission does not wipe unrelated metadata.
 */
export function toFormMetadataPayload(m: IFormRegistrationMetadata): TFormMetadataPayload {
    const purpose: TFormPurpose = m.purpose;
    const isOther = purpose !== 'registration' && purpose !== 'd_kelas';
    const allowMultiple = isOther && m.allow_multiple_submissions === true;
    const allowGuestSubmissions = isOther && m.allow_guest_submissions === true;
    const parsedMaxSubmissions = parseTeamSize(m.max_submissions);
    return {
        purpose,
        requires_form_id:
            typeof m.requires_form_id === 'string' && m.requires_form_id !== '' ? m.requires_form_id : null,
        registration_mode: isOther
            ? m.registration_mode === 'single'
                ? 'single'
                : null
            : (m.registration_mode ?? null),
        max_team_size: isOther ? null : parseTeamSize(m.max_team_size),
        team_size: isOther ? null : parseTeamSize(m.team_size),
        allow_multiple_submissions: allowMultiple,
        allow_guest_submissions: allowGuestSubmissions,
        max_submissions:
            allowMultiple &&
            parsedMaxSubmissions !== null &&
            Number.isInteger(parsedMaxSubmissions) &&
            parsedMaxSubmissions >= 2
                ? parsedMaxSubmissions
                : allowMultiple
                  ? 2
                  : 1,
        sections: parseBuilderSections(m.sections),
        referral_position: normalizeReferralPosition(m.referral_position),
        meeting_schedule_enabled: m.meeting_schedule_enabled === true,
    };
}
