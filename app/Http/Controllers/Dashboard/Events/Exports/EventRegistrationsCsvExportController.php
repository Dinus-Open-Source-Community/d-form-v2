<?php

namespace App\Http\Controllers\Dashboard\Events\Exports;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FormAnswer;
use App\Services\Registration\RegistrationAnswersSummarizer;
use App\Support\CsvCellSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationsCsvExportController extends Controller
{
    /**
     * Batas atas ukuran halaman: satu halaman memuat model `form_answers` beserta
     * JSON `answers`, jadi nilai env yang terlalu besar berakhir sebagai 500 memory limit.
     */
    private const MAX_PAGE_SIZE = 2000;

    public function __invoke(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        $summarizer = app(RegistrationAnswersSummarizer::class);

        $fileName = 'registrations-event-'.$event->id.'.csv';

        return response()->streamDownload(function () use ($event, $summarizer): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
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
            ], ',', '"', '');

            $this->streamRows($out, $event, $summarizer);

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Menulis seluruh baris CSV per halaman memakai keyset pagination
     * `(form_answers.created_at DESC, form_answers.id DESC)`.
     *
     * `created_at` hanya presisi detik dan tidak unik: tie lintas batas halaman
     * membuat offset melewatkan atau menulis ulang baris, dan OFFSET besar
     * mematerialisasi ulang hasil terurut pada setiap halaman. `id` (uuid) unik,
     * jadi pasangan `(created_at, id)` deterministik. Tanpa filter snapshot:
     * karena urutannya DESC, baris baru selalu berada sebelum kursor sehingga
     * tidak pernah tertulis dua kali.
     *
     * Precondition: `created_at` tidak diubah selama export, dan form tidak
     * di-soft-delete setelah kursor; baris yang memenuhi kondisi itu akan terlewat.
     * Baris `created_at` NULL (kolom nullable) ditulis oleh tail pass terpisah
     * karena NULL selalu berada di ekor urutan DESC dan tidak bisa dijangkau kursor.
     *
     * @param  resource  $out
     */
    private function streamRows($out, Event $event, RegistrationAnswersSummarizer $summarizer): void
    {
        $pageSize = self::resolvePageSize();
        $cursor = null;

        do {
            $answers = $this->answerPage($event, $cursor, $pageSize);

            foreach ($answers as $answer) {
                $this->writeRow($out, $answer, $summarizer);
            }

            $last = $answers->last();
            if ($last instanceof FormAnswer) {
                $cursor = [
                    (string) $last->getRawOriginal('created_at'),
                    (string) $last->getKey(),
                ];
            }
        } while ($answers->count() === $pageSize);

        $this->streamRowsWithoutCreatedAt($out, $event, $summarizer, $pageSize);
    }

    /**
     * Tail pass untuk baris ber-`created_at` NULL, dipaginasi keyset `id DESC`.
     *
     * @param  resource  $out
     */
    private function streamRowsWithoutCreatedAt(
        $out,
        Event $event,
        RegistrationAnswersSummarizer $summarizer,
        int $pageSize,
    ): void {
        $cursorId = null;

        do {
            $answers = $this->answerPageWithoutCreatedAt($event, $cursorId, $pageSize);

            foreach ($answers as $answer) {
                $this->writeRow($out, $answer, $summarizer);
            }

            $last = $answers->last();
            if ($last instanceof FormAnswer) {
                $cursorId = (string) $last->getKey();
            }
        } while ($answers->count() === $pageSize);
    }

    /**
     * Satu halaman submisi event, diurutkan `(created_at DESC, id DESC)` dengan kursor.
     *
     * @param  array{0: string, 1: string}|null  $cursor
     * @return Collection<int, FormAnswer>
     */
    private function answerPage(Event $event, ?array $cursor, int $pageSize): Collection
    {
        $query = $this->baseAnswerQuery($event)->whereNotNull('form_answers.created_at');

        if ($cursor !== null) {
            [$cursorCreatedAt, $cursorId] = $cursor;

            $query->where(function (Builder $boundary) use ($cursorCreatedAt, $cursorId): void {
                $boundary->where('form_answers.created_at', '<', $cursorCreatedAt)
                    ->orWhere(function (Builder $tie) use ($cursorCreatedAt, $cursorId): void {
                        $tie->where('form_answers.created_at', $cursorCreatedAt)
                            ->where('form_answers.id', '<', $cursorId);
                    });
            });
        }

        return $query
            ->orderByDesc('form_answers.created_at')
            ->orderByDesc('form_answers.id')
            ->limit($pageSize)
            ->get();
    }

    /**
     * @return Collection<int, FormAnswer>
     */
    private function answerPageWithoutCreatedAt(Event $event, ?string $cursorId, int $pageSize): Collection
    {
        $query = $this->baseAnswerQuery($event)->whereNull('form_answers.created_at');

        if ($cursorId !== null) {
            $query->where('form_answers.id', '<', $cursorId);
        }

        return $query
            ->orderByDesc('form_answers.id')
            ->limit($pageSize)
            ->get();
    }

    /**
     * Query dasar kedua pass; `forms` sudah di-join sehingga judulnya diambil sebagai
     * kolom agregat (bukan eager load tambahan satu query per halaman).
     */
    private function baseAnswerQuery(Event $event): Builder
    {
        return FormAnswer::query()
            ->join('forms', 'form_answers.form_id', '=', 'forms.id')
            ->select('form_answers.*')
            ->addSelect('forms.title as form_title')
            ->with(['user:id,name,email', 'teamLeaderSubmission.user:id,email'])
            ->whereNull('forms.deleted_at')
            ->where('forms.event_id', $event->id);
    }

    private static function resolvePageSize(): int
    {
        return min(self::MAX_PAGE_SIZE, max(1, (int) config('registration.event_export_page_size')));
    }

    /**
     * @param  resource  $out
     */
    private function writeRow($out, FormAnswer $answer, RegistrationAnswersSummarizer $summarizer): void
    {
        $user = $answer->user;
        $leaderEmail = $answer->teamLeaderSubmission?->user?->email ?? '';

        $summaryParts = [];
        foreach ($summarizer->summarize($answer) as $label => $value) {
            $summaryParts[] = $label.': '.$value;
        }

        $submittedAt = $answer->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '';

        fputcsv($out, array_map(CsvCellSanitizer::sanitize(...), [
            $answer->id,
            $answer->form_id,
            (string) ($answer->form_title ?? ''),
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
        ]), ',', '"', '');
    }
}
