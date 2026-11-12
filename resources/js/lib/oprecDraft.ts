export type TJsonValue = string | number | boolean | null | TJsonValue[] | { [key: string]: TJsonValue };

export interface IOprecDraft {
    step?: number;
    values: Record<string, TJsonValue>;
}

function isJsonValue(value: unknown): value is TJsonValue {
    if (value === null || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return true;
    }
    if (Array.isArray(value)) return value.every(isJsonValue);
    if (typeof value !== 'object') return false;
    return Object.values(value).every(isJsonValue);
}

function isDraftRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Serializes an OpRec draft without narrowing legacy JSON values to strings. */
export function serializeOprecDraft(draft: IOprecDraft): string {
    return JSON.stringify(draft);
}

/** Reads both the current draft shape and the legacy `{ values }` shape. */
export function parseOprecDraft(raw: string): IOprecDraft | null {
    let parsed: unknown;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return null;
    }
    if (!isDraftRecord(parsed) || !isDraftRecord(parsed.values)) return null;

    const values: Record<string, TJsonValue> = {};
    for (const [key, value] of Object.entries(parsed.values)) {
        if (isJsonValue(value)) values[key] = value;
    }

    return {
        step: typeof parsed.step === 'number' ? parsed.step : undefined,
        values,
    };
}

/** Narrows a form value to JSON-safe draft data; browser File values are omitted. */
export function toOprecDraftValue(value: unknown): TJsonValue | undefined {
    return isJsonValue(value) ? value : undefined;
}
