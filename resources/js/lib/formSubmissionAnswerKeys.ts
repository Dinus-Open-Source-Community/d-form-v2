/** Number of answer columns shown as a preview in the submissions table. */
export const SUBMISSION_ANSWER_KEY_PREVIEW_LIMIT = 4;

/** Any object that may hold submitted answers. */
export interface ISubmissionAnswerSource {
    answers?: Record<string, unknown> | null;
}

/** Bundle group shape needed to discover answer keys. */
export interface ISubmissionAnswerGroup {
    leader?: ISubmissionAnswerSource | null;
    members?: readonly ISubmissionAnswerSource[] | null;
}

export interface ISubmissionAnswerKeyInput {
    /** Field names in builder order; always first in the result. */
    fieldNames: readonly string[];
    submissions?: readonly ISubmissionAnswerSource[];
    groups?: readonly ISubmissionAnswerGroup[];
    /** Slice the result to the first N keys; omit for the full list. */
    limit?: number;
}

/**
 * Answer key order: builder field order first, then keys discovered in submissions, then bundle
 * leader/member answers, de-duplicated in first-seen order. Identical for preview and full lists.
 */
export function collectSubmissionAnswerKeys(input: ISubmissionAnswerKeyInput): string[] {
    const keys = new Set<string>(input.fieldNames);

    function addAnswers(source: ISubmissionAnswerSource | null | undefined): void {
        for (const key of Object.keys(source?.answers ?? {})) {
            keys.add(key);
        }
    }

    for (const submission of input.submissions ?? []) {
        addAnswers(submission);
    }

    for (const group of input.groups ?? []) {
        addAnswers(group.leader);
        for (const member of group.members ?? []) {
            addAnswers(member);
        }
    }

    const allKeys = [...keys];
    return input.limit === undefined ? allKeys : allKeys.slice(0, input.limit);
}
