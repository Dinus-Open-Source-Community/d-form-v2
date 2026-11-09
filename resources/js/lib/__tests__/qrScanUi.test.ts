import { describe, expect, it } from 'vitest';
import { extractQrCandidate } from '@/lib/qrScanUi';

describe('extractQrCandidate', () => {
    it('trims a plain scanned code', () => {
        expect(extractQrCandidate('  SUB-123  ')).toBe('SUB-123');
    });

    it('prefers application_id over other JSON keys', () => {
        expect(extractQrCandidate('{"application_id":"app-1","submission_id":"sub-1"}')).toBe('app-1');
    });

    it.each([
        ['{"submission_id":"sub-1","token":"t1"}', 'sub-1'],
        ['{"token":" t1 "}', 't1'],
        ['{"code":"c1"}', 'c1'],
        ['{"qr":"q1"}', 'q1'],
        ['{"email":"user@example.test"}', 'user@example.test'],
        ['{"id":"i1"}', 'i1'],
    ])('falls back through JSON keys for %s', (input: string, expected: string) => {
        expect(extractQrCandidate(input)).toBe(expected);
    });

    it('returns the raw JSON when the candidate is blank', () => {
        const raw = '{"application_id":"   "}';

        expect(extractQrCandidate(raw)).toBe(raw);
    });

    it('returns the raw text when the JSON is malformed', () => {
        const raw = '{"application_id":}';

        expect(extractQrCandidate(raw)).toBe(raw);
    });

    it('returns the raw text when JSON has no usable string candidate', () => {
        expect(extractQrCandidate('{"id":123}')).toBe('{"id":123}');
        expect(extractQrCandidate('[1,2]')).toBe('[1,2]');
    });
});
