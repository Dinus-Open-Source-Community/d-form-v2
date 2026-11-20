<?php

namespace App\Support;

use App\Rules\FieldInputPattern;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Sumber tunggal pola input ketat untuk `metadata.type` field `input`.
 *
 * Padanan backend dari `resources/js/lib/fieldInputPatterns.ts`: pola, normalisasi,
 * dan pesan Indonesia didefinisikan sekali di sini lalu dipakai builder validasi.
 */
final class FieldInputPatterns
{
    /** Subtype `metadata.type` yang diterima builder untuk field `input`.
     * @var list<string>
     */
    public const METADATA_TYPES = ['text', 'number', 'email', 'password', 'tel', 'nik', 'nim', 'postal_code'];

    /** Pola ber-anchor; `tel` diuji setelah {@see self::normalizeTelephone()}.
     * @var array<string, string>
     */
    private const PATTERNS = [
        'number'      => '/^\d+$/',
        'tel'         => '/^\+?\d{9,15}$/',
        'nik'         => '/^\d{16}$/',
        'nim'         => '/^\d{5,20}$/',
        'postal_code' => '/^\d{5}$/',
    ];

    /** Pesan gagal berbahasa Indonesia per subtype berpola.
     * @var array<string, string>
     */
    private const MESSAGES = [
        'number'      => 'Hanya angka yang boleh diisi.',
        'tel'         => 'Nomor WhatsApp hanya boleh berisi angka, 9 sampai 15 digit.',
        'nik'         => 'NIK harus 16 angka.',
        'nim'         => 'NIM harus berupa angka, 5 sampai 20 digit.',
        'postal_code' => 'Kode pos harus 5 angka.',
    ];

    /** Karakter pemisah nomor telepon: spasi, tanda hubung, titik, dan kurung.
     * @var list<string>
     */
    private const TELEPHONE_SEPARATORS = [' ', '-', '.', '(', ')'];

    /** Laravel rules untuk satu subtype; `email` tetap memakai rule bawaan Laravel.
     * @return array<int, string|ValidationRule>
     */
    public static function rulesFor(string $subtype): array
    {
        if ($subtype === 'email') {
            return ['email'];
        }

        $pattern = self::patternFor($subtype);

        if ($pattern === null) {
            return [];
        }

        return [
            new FieldInputPattern(
                $pattern,
                self::messageFor($subtype) ?? 'Format isian tidak valid.',
                $subtype === 'tel' ? self::normalizeTelephone(...) : null,
            ),
        ];
    }

    /** Regex ber-anchor untuk subtype yang butuh bentuk ketat, atau null bila tidak ada. */
    public static function patternFor(string $subtype): ?string
    {
        return self::PATTERNS[$subtype] ?? null;
    }

    /** Pesan gagal berbahasa Indonesia untuk subtype berpola, atau null bila tidak ada. */
    public static function messageFor(string $subtype): ?string
    {
        return self::MESSAGES[$subtype] ?? null;
    }

    /** Buang spasi, tanda hubung, titik, dan kurung; tanda plus di awal dipertahankan. */
    public static function normalizeTelephone(string $value): string
    {
        return str_replace(self::TELEPHONE_SEPARATORS, '', $value);
    }
}
