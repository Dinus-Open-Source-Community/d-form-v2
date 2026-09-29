<?php

namespace App\Services\Broadcasting;

use App\Enums\EmailDatasetSourceType;
use App\Models\EmailDataset;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;

/**
 * Resolve dataset sources menjadi daftar [name, email].
 * Terisolasi di modul broadcasting; tidak mengubah resolver modul lain.
 */
class BroadcastDatasetResolver
{
    /**
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @return array<int, array{name:string|null, email:string, source:string}>
     */
    public function resolve(array $datasets): array
    {
        $rows = [];

        foreach ($datasets as $dataset) {
            $type = $dataset['type'] ?? null;

            if ($type === EmailDatasetSourceType::EventParticipants->value) {
                foreach ($this->eventParticipants($dataset['event_id'] ?? $dataset['id'] ?? null) as $row) {
                    $rows[] = $row + ['source' => 'event_participants'];
                }
            } elseif ($type === EmailDatasetSourceType::RecruitmentApplicants->value) {
                foreach ($this->recruitmentApplicants($dataset['period_id'] ?? $dataset['id'] ?? null) as $row) {
                    $rows[] = $row + ['source' => 'recruitment_applicants'];
                }
            } elseif ($type === EmailDatasetSourceType::Users->value) {
                foreach ($this->users() as $row) {
                    $rows[] = $row + ['source' => 'users'];
                }
            } elseif ($type === EmailDatasetSourceType::Custom->value && ! empty($dataset['id'])) {
                foreach ($this->customDataset((string) $dataset['id']) as $row) {
                    $rows[] = $row + ['source' => 'custom'];
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{name:string|null, email:string}>
     */
    public function eventParticipants(?string $eventId = null): array
    {
        $query = FormAnswer::query()
            ->with(['user', 'form'])
            ->whereListedForOrganizerParticipantRoster();

        if ($eventId !== null && $eventId !== '') {
            $query->whereHas('form', fn ($q) => $q->where('event_id', $eventId));
        }

        $rows = [];

        foreach ($query->cursor() as $answer) {
            /** @var FormAnswer $answer */
            $email = $answer->user?->email ?? $answer->invited_email;
            $email = is_string($email) ? strtolower(trim($email)) : '';

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $rows[] = [
                'name' => $answer->user?->name,
                'email' => $email,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{name:string|null, email:string}>
     */
    public function recruitmentApplicants(?string $periodId = null): array
    {
        $query = RecruitmentApplication::query();

        if ($periodId !== null && $periodId !== '') {
            $query->where('recruitment_period_id', $periodId);
        }

        $rows = [];

        foreach ($query->cursor(['full_name', 'personal_email', 'student_email']) as $app) {
            $email = strtolower(trim((string) ($app->personal_email !== '' ? $app->personal_email : $app->student_email)));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $rows[] = [
                'name' => $app->full_name ?: null,
                'email' => $email,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{name:string|null, email:string}>
     */
    public function users(): array
    {
        $rows = [];

        foreach (User::query()->cursor(['name', 'email']) as $user) {
            $email = strtolower(trim((string) $user->email));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $rows[] = [
                'name' => $user->name,
                'email' => $email,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{name:string|null, email:string}>
     */
    public function customDataset(string $datasetId): array
    {
        $dataset = EmailDataset::query()->find($datasetId);

        if ($dataset === null) {
            return [];
        }

        return $dataset->recipients()
            ->get(['name', 'email'])
            ->map(fn ($r) => ['name' => $r->name, 'email' => strtolower(trim((string) $r->email))])
            ->filter(fn ($r) => $r['email'] !== '' && filter_var($r['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }

    /**
     * @return array{total:int, unique:int, duplicates:int, duplicate_emails:array<int,string>}
     */
    public function duplicateSummary(array $emails): array
    {
        $normalized = array_map(fn ($e) => strtolower(trim((string) $e)), $emails);
        $counts = array_count_values($normalized);
        $duplicateEmails = [];

        foreach ($counts as $email => $count) {
            if ($count > 1) {
                $duplicateEmails[] = $email;
            }
        }

        return [
            'total' => count($normalized),
            'unique' => count($counts),
            'duplicates' => count($normalized) - count($counts),
            'duplicate_emails' => array_values($duplicateEmails),
        ];
    }
}
