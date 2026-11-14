<?php

namespace App\Services\Broadcasting;

use App\Enums\EmailDatasetSourceType;
use App\Models\EmailDataset;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\User;
use Generator;

/** Resolve dataset sources menjadi daftar [name, email]. */
class BroadcastDatasetResolver
{
    /**
     * Lazy equivalent of {@see self::resolve()}; each source streams in primary-key order for deterministic snapshots.
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @return Generator<int, array{name:string|null, email:string, source:string}>
     */
    public function stream(array $datasets): Generator
    {
        foreach ($datasets as $dataset) {
            $type = $dataset['type'] ?? null;

            if ($type === EmailDatasetSourceType::EventParticipants->value) {
                foreach ($this->streamEventParticipants($dataset['event_id'] ?? $dataset['id'] ?? null) as $row) {
                    yield $row + ['source' => 'event_participants'];
                }
            } elseif ($type === EmailDatasetSourceType::RecruitmentApplicants->value) {
                foreach ($this->streamRecruitmentApplicants($dataset['period_id'] ?? $dataset['id'] ?? null) as $row) {
                    yield $row + ['source' => 'recruitment_applicants'];
                }
            } elseif ($type === EmailDatasetSourceType::Users->value) {
                foreach ($this->streamUsers() as $row) {
                    yield $row + ['source' => 'users'];
                }
            } elseif ($type === EmailDatasetSourceType::Custom->value && ! empty($dataset['id'])) {
                foreach ($this->streamCustomDataset((string) $dataset['id']) as $row) {
                    yield $row + ['source' => 'custom'];
                }
            }
        }
    }

    /**
     *
     * @param  array<int, array{type:string, id?:string|null, event_id?:string|null, period_id?:string|null}>  $datasets
     * @return array<int, array{name:string|null, email:string, source:string}>
     *
     */
    public function resolve(array $datasets): array
    {
        return iterator_to_array($this->stream($datasets), false);
    }

    /**
     *
     * Lazily yield participant roster rows; callers that need the array keep
     * using {@see self::eventParticipants()}.
     *
     * @return Generator<int, array{name:string|null, email:string}>
     *
     */
    public function streamEventParticipants(?string $eventId = null): Generator
    {
        $query = FormAnswer::query()
            ->with(['user', 'form'])
            ->whereListedForOrganizerParticipantRoster();

        if ($eventId !== null && $eventId !== '') {
            $query->whereHas('form', fn ($q) => $q->where('event_id', $eventId));
        }

        foreach ($query->lazyById() as $answer) {
            /** @var FormAnswer $answer */
            $email = $answer->user?->email ?? $answer->invited_email;
            $email = is_string($email) ? strtolower(trim($email)) : '';

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            yield [
                'name' => $answer->user?->name,
                'email' => $email,
            ];
        }
    }

    /**
     *
     * @return array<int, array{name:string|null, email:string}>
     *
     */
    public function eventParticipants(?string $eventId = null): array
    {
        return iterator_to_array($this->streamEventParticipants($eventId), false);
    }

    /**
     *
     * @return Generator<int, array{name:string|null, email:string}>
     *
     */
    public function streamRecruitmentApplicants(?string $periodId = null): Generator
    {
        $query = RecruitmentApplication::query();

        if ($periodId !== null && $periodId !== '') {
            $query->where('recruitment_period_id', $periodId);
        }

        foreach ($query->select(['id', 'full_name', 'personal_email', 'student_email'])->lazyById() as $app) {
            $email = strtolower(trim((string) ($app->personal_email !== '' ? $app->personal_email : $app->student_email)));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            yield [
                'name' => $app->full_name ?: null,
                'email' => $email,
            ];
        }
    }

    /**
     *
     * @return array<int, array{name:string|null, email:string}>
     *
     */
    public function recruitmentApplicants(?string $periodId = null): array
    {
        return iterator_to_array($this->streamRecruitmentApplicants($periodId), false);
    }

    /**
     *
     * @return Generator<int, array{name:string|null, email:string}>
     *
     */
    public function streamUsers(): Generator
    {
        foreach (User::query()->select(['id', 'name', 'email'])->lazyById() as $user) {
            $email = strtolower(trim((string) $user->email));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            yield [
                'name' => $user->name,
                'email' => $email,
            ];
        }
    }

    /**
     *
     * @return array<int, array{name:string|null, email:string}>
     *
     */
    public function users(): array
    {
        return iterator_to_array($this->streamUsers(), false);
    }

    /**
     *
     * @return Generator<int, array{name:string|null, email:string}>
     *
     */
    public function streamCustomDataset(string $datasetId): Generator
    {
        $dataset = EmailDataset::query()->find($datasetId);

        if ($dataset === null) {
            return;
        }

        foreach ($dataset->recipients()->select(['id', 'name', 'email'])->lazyById() as $recipient) {
            $email = strtolower(trim((string) $recipient->email));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            yield ['name' => $recipient->name, 'email' => $email];
        }
    }

    /**
     *
     * @return array<int, array{name:string|null, email:string}>
     *
     */
    public function customDataset(string $datasetId): array
    {
        return iterator_to_array($this->streamCustomDataset($datasetId), false);
    }
}
