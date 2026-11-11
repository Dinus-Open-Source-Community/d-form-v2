<?php

namespace App\Http\Controllers\Dashboard\Events\Exports;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationAnswersSummarizer;
use App\Support\EventExportAuditLogger;
use App\Support\Export\CsvStream;
use App\Support\Export\KeysetMergeIterator;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationsCsvExportController extends Controller
{
    /**
     * Batas atas ukuran halaman: satu halaman memuat model `form_answers` beserta
     * JSON `answers`, jadi nilai env yang terlalu besar berakhir sebagai 500 memory limit.
     */
    private const MAX_PAGE_SIZE = 2000;

    /** Kontrak kolom CSV export registrasi event; urutan tidak boleh berubah. */
    private const COLUMN_HEADER = [
        'submission_id',
        'form_id',
        'form_title',
        'user_name',
        'user_email',
        'registration_code',
        'review_status',
        'group_token',
        'registration_role',
        'member_confirmation_status',
        'leader_email',
        'submitted_at',
        'answers_summary',
    ];

    public function __invoke(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        $summarizer = app(RegistrationAnswersSummarizer::class);

        $fileName = 'registrations-event-'.$event->id.'.csv';
        $actorId = auth()->id();
        $auditLogger = app(EventExportAuditLogger::class);

        return response()->streamDownload(function () use ($event, $summarizer, $actorId, $auditLogger): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            $rowCount = 0;

            try {
                CsvStream::write($out, self::COLUMN_HEADER, $this->rows($event, $summarizer), $rowCount);
            } finally {
                fclose($out);

                // Audit tetap jalan saat generator/DB gagal atau klien memutus
                // stream: `$rowCount` berisi baris data yang benar-benar sudah ditulis.
                $auditLogger->record($actorId, (string) $event->getKey(), 'registrations', $rowCount);
            }
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Seluruh baris CSV berurutan `(created_at DESC, id DESC)`.
     *
     * `created_at` hanya presisi detik dan tidak unik, jadi `id` menjadi
     * tie-breaker: keyset `(created_at, id)` deterministik tanpa OFFSET. Tanpa
     * filter snapshot: karena urutannya DESC, baris baru selalu berada sebelum
     * kursor sehingga tidak pernah tertulis dua kali.
     *
     * Snapshot yang diambil adalah daftar form + judulnya, sekali di awal export.
     * Baris jawaban tetap dibaca live, jadi: form yang di-soft-delete setelah
     * export mulai tetap ikut (tidak memotong stream di tengah), dan form yang
     * ditambahkan setelah export mulai tidak ikut sama sekali — bukan muncul
     * dengan `form_title` kosong. Baris `created_at` NULL berada di luar total
     * order sehingga ditulis oleh tail pass `id DESC`.
     *
     * @return \Generator<int, list<string>>
     */
    private function rows(Event $event, RegistrationAnswersSummarizer $summarizer): \Generator
    {
        $pageSize = self::resolvePageSize();

        // Satu query kecil per export (bukan per halaman): id form mempartisi
        // stream keyset, judulnya dipakai untuk kolom CSV tanpa join per halaman.
        /** @var array<string, string|null> $formTitles */
        $formTitles = $event->forms()->pluck('title', 'id')->all();
        $formIds = array_keys($formTitles);

        foreach ($this->datedAnswers($formIds, $pageSize) as $answer) {
            yield $this->row($answer, $formTitles, $summarizer);
        }

        $undated = new KeysetPageIterator(
            $this->answerQuery($formIds)->whereNull('form_answers.created_at')
        );

        $undatedPage = new KeysetPage('form_answers.id', 'form_answers.id', descending: true, pageSize: $pageSize);

        foreach ($undated->iterate($undatedPage) as $answer) {
            yield $this->row($answer, $formTitles, $summarizer);
        }
    }

    /**
     * Baris ber-`created_at` non-NULL: satu stream keyset per form, digabung
     * k-way supaya urutan global `(created_at DESC, id DESC)` tetap terjaga.
     *
     * Satu stream per form wajib: `WHERE form_id IN (...)` membuat MySQL 9
     * meninggalkan index `form_answers (form_id, created_at, id)` dan memindai
     * `form_answers_created_at_index` (±26 s untuk 151k baris), sedangkan stream
     * per form membaca index komposit sebagai `Backward index scan` tanpa sort.
     *
     * Ukuran halaman per stream dibagi jumlah stream supaya puncak memori tetap
     * sebanding dengan satu halaman export (tiap stream menyimpan satu page model).
     *
     * @param  list<string>  $formIds
     * @return \Generator<int, FormAnswer>
     */
    private function datedAnswers(array $formIds, int $pageSize): \Generator
    {
        $streams = [];

        foreach ($formIds as $formId) {
            $streams[] = new KeysetPageIterator(
                $this->answerQuery([$formId])->whereNotNull('form_answers.created_at')
            );
        }

        $perStreamPageSize = max(1, (int) ceil($pageSize / max(1, count($streams))));

        $page = new KeysetPage(
            'form_answers.created_at',
            'form_answers.id',
            descending: true,
            pageSize: $perStreamPageSize,
        );

        foreach ((new KeysetMergeIterator($page, $streams))->iterate() as $answer) {
            yield $answer;
        }
    }

    /**
     * Query dasar semua pass: hanya `form_id` (tanpa join `forms`), karena judul
     * diambil dari snapshot form event dan `forms.deleted_at` sudah difilter saat
     * snapshot dibentuk (`SoftDeletes` pada model `Form`).
     *
     * @param  list<string>  $formIds
     */
    private function answerQuery(array $formIds): Builder
    {
        return FormAnswer::query()
            ->select('form_answers.*')
            ->with(['user:id,name,email', 'teamLeaderSubmission.user:id,email'])
            ->whereIn('form_id', $formIds);
    }

    private static function resolvePageSize(): int
    {
        return min(self::MAX_PAGE_SIZE, max(1, (int) config('registration.event_export_page_size')));
    }

    /**
     * @param  array<string, string|null>  $formTitles
     * @return list<string>
     */
    private function row(FormAnswer $answer, array $formTitles, RegistrationAnswersSummarizer $summarizer): array
    {
        $user = $answer->user;
        $leaderEmail = $answer->teamLeaderSubmission?->user?->email ?? '';

        $summaryParts = [];
        foreach ($summarizer->summarize($answer) as $label => $value) {
            $summaryParts[] = $label.': '.$value;
        }

        $submittedAt = $answer->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '';

        return [
            $answer->id,
            $answer->form_id,
            (string) ($formTitles[(string) $answer->form_id] ?? ''),
            $user?->name ?? '',
            $user?->email ?? '',
            (string) ($answer->registration_code ?? ''),
            $answer->review_status?->value ?? '',
            $answer->group_token ?? '',
            $answer->registration_role?->value ?? '',
            $answer->member_confirmation_status?->value ?? '',
            $leaderEmail,
            $submittedAt,
            implode(' | ', $summaryParts),
        ];
    }
}
