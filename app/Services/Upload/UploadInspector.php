<?php

namespace App\Services\Upload;

use App\Enums\UploadPurpose;
use Illuminate\Http\UploadedFile;

/**
 * Satu-satunya implementasi validasi isi unggahan.
 *
 * Dipakai oleh rule HTTP ({@see \App\Rules\SafeUploadFile}, menghasilkan 422 per
 * field) dan oleh penyimpanan ({@see SafeUploadStorage}, pertahanan terakhir bila
 * ada jalur yang melewati rule). Sniff memakai `finfo_file` pada file sementara;
 * MIME dari klien dan ekstensi nama hanya dipakai sebagai pembanding.
 */
final class UploadInspector
{
    /**
     * MIME yang tidak pernah boleh masuk, apa pun hasil sniff-nya.
     *
     * @var list<string>
     */
    private const BLOCKED_MIMES = [
        'text/html',
        'application/xhtml+xml',
        'application/x-php',
        'application/x-httpd-php',
        'text/x-php',
        'text/javascript',
        'application/javascript',
        'application/x-sh',
        'application/x-shellscript',
        'text/x-shellscript',
        'image/svg+xml',
    ];

    /**
     * Ekstensi aktif/executable yang ditolak walau muncul sebagai ekstensi
     * tengah pada nama ganda (`shell.php.jpg`).
     *
     * @var list<string>
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'inc',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'svg', 'svgz',
        'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'ps1', 'jar', 'py', 'rb', 'pl',
    ];

    /**
     * MIME klien yang generik dan karena itu tidak dipakai sebagai pembanding.
     *
     * @var list<string>
     */
    private const NEUTRAL_CLIENT_MIMES = ['', 'application/octet-stream', 'binary/octet-stream', 'application/unknown'];

    /**
     * Validasi satu unggahan. Mengembalikan pesan error publik atau null bila sah.
     *
     * @param  list<string>|null  $allowedExtensions  Override dinamis (form field);
     *                                                null berarti pakai daftar purpose.
     * @param  int|null  $maxSizeKb  Batas per field; selalu di-clamp ke batas purpose.
     */
    public static function inspect(
        UploadedFile $file,
        UploadPurpose $purpose,
        ?array $allowedExtensions = null,
        ?int $maxSizeKb = null,
    ): ?string {
        if ($file->isValid() === false) {
            return 'The uploaded file is invalid.';
        }

        $extensions = self::normalizeExtensions($allowedExtensions ?? $purpose->extensions());
        $mimes = $allowedExtensions === null
            ? $purpose->mimes()
            : self::mimesForExtensions($extensions);

        $limitKb = min($maxSizeKb !== null && $maxSizeKb > 0 ? $maxSizeKb : $purpose->maxSizeKb(), $purpose->maxSizeKb());
        $size = $file->getSize();
        if ($size === null || $size > $limitKb * 1024) {
            return 'The file may not be greater than '.self::formatLimit($limitKb).'.';
        }

        if (self::hasUnsafeName($file)) {
            return 'The uploaded file name is invalid.';
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());
        if ($clientExtension === '' || in_array($clientExtension, $extensions, true) === false) {
            return 'The uploaded file format is not allowed.';
        }

        // Nama ganda: semua ekstensi pada nama diperiksa, bukan hanya yang terakhir.
        foreach (self::nameExtensions($file->getClientOriginalName()) as $segmented) {
            if (in_array($segmented, self::BLOCKED_EXTENSIONS, true)) {
                return 'The uploaded file type is not allowed.';
            }
        }

        $mime = self::sniffedMime($file);
        if ($mime === null) {
            return 'The uploaded file type could not be detected.';
        }

        $family = self::familyForMime($mime);
        if ($family === null || in_array($mime, self::BLOCKED_MIMES, true)) {
            return 'The uploaded file type is not allowed.';
        }

        if (in_array($mime, $mimes, true) === false) {
            return 'The uploaded file type is not allowed.';
        }

        $clientMime = strtolower(trim($file->getClientMimeType()));

        if ($family === 'zip' && in_array($clientExtension, ['docx', 'xlsx', 'pptx'], true)) {
            if (in_array($clientExtension, $extensions, true) === false) {
                return 'The uploaded file format is not allowed.';
            }

            if (self::clientMimeMatches($clientMime, $mime) === false) {
                return 'The uploaded file type does not match its content.';
            }

            return self::inspectOfficeOpenXml($file, $clientExtension);
        }

        $canonical = self::extensionForMime($mime);
        if ($canonical === null) {
            return 'The uploaded file type is not allowed.';
        }

        // Ekstensi klien harus sama dengan ekstensi kanonik hasil sniff (jpg/jpeg
        // satu keluarga; csv/txt sama-sama teks). Nama palsu/berganti ekstensi gugur.
        $acceptedClientExtensions = $family === 'text'
            ? ['csv', 'txt']
            : ($canonical === 'jpg' ? ['jpg', 'jpeg'] : [$canonical]);

        if (in_array($clientExtension, $acceptedClientExtensions, true) === false) {
            return 'The uploaded file type does not match its content.';
        }

        if (in_array($clientMime, self::NEUTRAL_CLIENT_MIMES, true) === false
            && self::clientMimeMatches($clientMime, $mime) === false) {
            return 'The uploaded file type does not match its content.';
        }

        return match ($family) {
            'image' => self::inspectImage($file, $mime),
            'pdf' => self::inspectPdf($file),
            'zip' => self::hasMagicBytes($file, "PK\x03\x04") ? null : 'The archive content is invalid.',
            'text' => self::inspectText($file),
            default => 'The uploaded file type is not allowed.',
        };
    }

    /** MIME asli dari isi file (finfo pada file sementara), bukan dari header klien. */
    public static function sniffedMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    /** Ekstensi kanonik untuk MIME hasil sniff (dipakai saat menamai file tersimpan). */
    public static function extensionForMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'text/csv', 'application/csv', 'application/vnd.ms-excel' => 'csv',
            'text/plain' => 'txt',
            default => null,
        };
    }

    /** Return the safe stored extension after package-aware content inspection. */
    public static function extensionForUpload(UploadedFile $file): ?string
    {
        $mime = self::sniffedMime($file);
        if ($mime === 'application/zip') {
            $extension = strtolower($file->getClientOriginalExtension());
            if (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
                return $extension;
            }
        }

        return $mime === null ? null : self::extensionForMime($mime);
    }

    /** @return list<string> */
    private static function normalizeExtensions(array $extensions): array
    {
        $normalized = array_map(static fn (mixed $ext): string => strtolower(trim((string) $ext)), $extensions);

        return array_values(array_unique(array_filter($normalized, static fn (string $ext): bool => $ext !== '')));
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private static function mimesForExtensions(array $extensions): array
    {
        $mimes = [];
        foreach ($extensions as $extension) {
            $mapped = match ($extension) {
                'jpg', 'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
                'webp' => ['image/webp'],
                'gif' => ['image/gif'],
                'pdf' => ['application/pdf'],
                'zip' => ['application/zip'],
                'docx', 'xlsx', 'pptx' => ['application/zip'],
                'csv', 'txt' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
                default => [],
            };
            $mimes = array_merge($mimes, $mapped);
        }

        return array_values(array_unique($mimes));
    }

    /** Keluarga isi file yang menentukan pemeriksaan magic byte. */
    private static function familyForMime(string $mime): ?string
    {
        return match (true) {
            in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) => 'image',
            $mime === 'application/pdf' => 'pdf',
            in_array($mime, ['application/zip', 'application/x-zip-compressed'], true) => 'zip',
            in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true) => 'text',
            default => null,
        };
    }

    /** MIME klien dianggap cocok bila sama setelah normalisasi alias, atau sama-sama teks. */
    private static function clientMimeMatches(string $clientMime, string $sniffedMime): bool
    {
        $aliases = [
            'image/jpg' => 'image/jpeg',
            'image/pjpeg' => 'image/jpeg',
            'application/x-pdf' => 'application/pdf',
            'application/acrobat' => 'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/zip',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'application/zip',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'application/zip',
            'text/x-csv' => 'text/csv',
            'application/vnd.ms-excel' => 'text/csv',
            'application/csv' => 'text/csv',
        ];

        $client = $aliases[$clientMime] ?? $clientMime;
        $sniffed = $aliases[$sniffedMime] ?? $sniffedMime;

        if ($client === $sniffed) {
            return true;
        }

        return self::familyForMime($client) === 'text' && self::familyForMime($sniffed) === 'text';
    }

    /** @return list<string> */
    private static function nameExtensions(string $name): array
    {
        $parts = explode('.', strtolower($name));
        array_shift($parts);

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /** Tolak path traversal, karakter kontrol, stream alternatif, dan nama tersembunyi. */
    private static function hasUnsafeName(UploadedFile $file): bool
    {
        $name = $file->getClientOriginalName();

        return $name === ''
            || basename($name) !== $name
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, ':')
            || str_starts_with($name, '.')
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1;
    }

    private static function formatLimit(int $maxSizeKb): string
    {
        return $maxSizeKb % 1024 === 0
            ? intdiv($maxSizeKb, 1024).' MB'
            : $maxSizeKb.' KB';
    }

    private static function hasMagicBytes(UploadedFile $file, string $magic): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, strlen($magic));
        fclose($handle);

        return is_string($head) && hash_equals($magic, $head);
    }

    /**
     * PDF: magic `%PDF-`, EOF, dan tanpa action aktif yang dapat mengeksekusi
     * kode atau mengirim data. `/EmbeddedFile` dan `/Encrypt` ditolak fail-closed
     * karena isi lampiran atau isi yang dilindungi tidak dapat diperiksa dari
     * byte mentah.
     *
     * Batas baca 10 MB: berkas lebih besar sudah ditolak pada cek ukuran, jadi
     * `file_get_contents` di sini tidak bisa membaca berkas raksasa.
     */
    private static function inspectPdf(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return 'The PDF content is invalid.';
        }

        $contents = file_get_contents($path);
        if ($contents === false || str_starts_with($contents, '%PDF-') === false) {
            return 'The PDF header is invalid.';
        }

        if (str_contains(substr($contents, -1024), '%%EOF') === false) {
            return 'The PDF is incomplete.';
        }

        if (preg_match('/\/(?:Encrypt|EmbeddedFile)\b/i', $contents) === 1) {
            return 'Encrypted or embedded PDF content is not allowed.';
        }

        if (preg_match('/\/(?:JavaScript|JS|OpenAction|AA|Launch|RichMedia|SubmitForm|ImportData|GoToR|XFA)\b/i', $contents) === 1) {
            return 'The PDF contains active content that is not allowed.';
        }

        return null;
    }

    /** Teks (CSV/TXT): UTF-8 tanpa byte kontrol biner, termasuk NUL. */
    private static function inspectText(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        $contents = $path === false ? false : file_get_contents($path);
        if ($contents === false || mb_check_encoding($contents, 'UTF-8') === false) {
            return 'The text file content is invalid.';
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $contents) === 1) {
            return 'The text file contains binary control bytes.';
        }

        return null;
    }

    private static function inspectOfficeOpenXml(UploadedFile $file, string $extension): ?string
    {
        if (class_exists(\ZipArchive::class) === false) {
            return 'The Office document could not be inspected safely.';
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return 'The Office document content is invalid.';
        }

        $archive = new \ZipArchive();
        if ($archive->open($path) !== true) {
            return 'The Office document package is malformed.';
        }

        $expandedBytes = 0;
        $maxExpandedBytes = max(1, (int) config('events.forms.upload_max_expanded_size_kb', 20 * 1024)) * 1024;
        $hasContentTypes = false;
        $hasRequiredPart = false;
        $requiredPart = match ($extension) {
            'xlsx' => 'xl/workbook.xml',
            'pptx' => 'ppt/presentation.xml',
            default => 'word/document.xml',
        };

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->statIndex($index);
            $name = is_array($entry) ? ($entry['name'] ?? null) : null;
            if (! is_string($name) || self::hasUnsafeArchiveEntryName($name)) {
                $archive->close();

                return 'The Office document package contains an unsafe path.';
            }

            if (preg_match('/(?:^|\/)(?:vbaProject\.bin|vbaData\.xml)$/i', $name) === 1) {
                $archive->close();

                return 'Macro-enabled Office documents are not allowed.';
            }

            $operationsSystem = 0;
            $externalAttributes = 0;
            if ($archive->getExternalAttributesIndex($index, $operationsSystem, $externalAttributes)
                && $operationsSystem === \ZipArchive::OPSYS_UNIX
                && ((($externalAttributes >> 16) & 0170000) === 0120000)) {
                $archive->close();

                return 'The Office document package contains an unsafe entry.';
            }

            $uncompressedSize = is_array($entry) ? (int) ($entry['size'] ?? 0) : 0;
            $expandedBytes += $uncompressedSize;
            if ($uncompressedSize < 0 || $expandedBytes > $maxExpandedBytes) {
                $archive->close();

                return 'The Office document expands beyond the safety limit.';
            }

            $hasContentTypes = $hasContentTypes || $name === '[Content_Types].xml';
            $hasRequiredPart = $hasRequiredPart || $name === $requiredPart;
        }

        $contentTypes = $archive->getFromName('[Content_Types].xml');
        $requiredContents = $archive->getFromName($requiredPart);
        $archive->close();

        if (! $hasContentTypes
            || ! $hasRequiredPart
            || ! is_string($contentTypes)
            || ! is_string($requiredContents)
            || preg_match('/<Types\b/i', $contentTypes) !== 1
            || preg_match('/<\/Types>/i', $contentTypes) !== 1
            || preg_match('/macroEnabled|vbaProject/i', $contentTypes) === 1
            || ! self::isWellFormedXml($contentTypes)
            || ! self::isWellFormedXml($requiredContents)) {
            return 'The Office document package is malformed.';
        }

        return null;
    }

    private static function isWellFormedXml(string $contents): bool
    {
        if (! class_exists(\DOMDocument::class)) {
            return false;
        }

        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $document->resolveExternals = false;
            $document->substituteEntities = false;

            return $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    private static function hasUnsafeArchiveEntryName(string $name): bool
    {
        if ($name === ''
            || str_starts_with($name, '/')
            || str_starts_with($name, '\\')
            || str_contains($name, ':')
            || str_contains($name, "\0")) {
            return true;
        }

        foreach (preg_split('/[\\\\\/]/', $name) ?: [] as $segment) {
            if ($segment === '..' || $segment === '.') {
                return true;
            }
        }

        return false;
    }

    /**
     * Gambar: cek header dulu (`getimagesize`, tanpa decode) agar decompression
     * bomb tidak pernah mencapai decoder, baru decode penuh sebagai bukti isi.
     *
     * Memori terburuk: cap 12M piksel × 4 byte truecolor ≈ 48 MB, ditambah
     * buffer sumber (≤5 MB) dan hasil re-encode (≤5 MB) → puncak ~58 MB/request.
     */
    private static function inspectImage(UploadedFile $file, string $mime): ?string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return 'The image content is invalid.';
        }

        $image = @getimagesize($path);
        if ($image === false || (is_string($image['mime'] ?? null) && strtolower($image['mime']) !== $mime)) {
            return 'The image content does not match its detected format.';
        }

        $width = $image[0] ?? 0;
        $height = $image[1] ?? 0;
        $maxPixels = (int) config('events.forms.upload_max_pixels', 12_000_000);
        $maxDimension = (int) config('events.forms.upload_max_dimension', 8000);

        if (is_int($width) === false || is_int($height) === false || $width < 1 || $height < 1) {
            return 'The image dimensions are not allowed.';
        }

        if ($width > $maxDimension || $height > $maxDimension || $width * $height > $maxPixels) {
            return 'The image dimensions are not allowed.';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return 'The image content could not be read.';
        }

        $decoded = @imagecreatefromstring($contents);
        if ($decoded === false) {
            return 'The image content could not be fully decoded.';
        }

        imagedestroy($decoded);

        return null;
    }
}
