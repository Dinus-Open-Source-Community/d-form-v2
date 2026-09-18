<?php

namespace Database\Seeders;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Enums\FormAnswerReviewStatus;
use App\Enums\Recruitment\ApplicationResult;
use App\Enums\Recruitment\ApplicationStage;
use App\Enums\Recruitment\InterviewStatus;
use App\Enums\Recruitment\RecruitmentPeriodStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentDivision;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;
use App\Models\Recruitment\RecruitmentInterviewerDivision;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Mail\ScanTestQrMail;
use App\Services\Recruitment\RecruitmentQrPngGenerator;
use App\Services\Registration\RegistrationCodeIssuer;
use App\Services\Registration\RegistrationQrPngGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Scan-test seeder. Registered in DatabaseSeeder, so it runs on every
 * `php artisan db:seed` (still runnable standalone via
 * `php artisan db:seed --class=ScanTestSeeder`).
 *
 * Creates 20 event registrations (Accepted, spread across Published events)
 * + 20 oprec applicants (stage Interview + Scheduled interview), all
 * scannable, plus QR PNGs under storage/app/scan-test/.
 *
 * Safe to re-run: every row is firstOrCreate/firstOrNew keyed on a unique
 * column, existing rows are normalised back to scannable state, never
 * duplicated.
 */
class ScanTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->purgeStaleQrFiles();

        $eventCodes = $this->seedEventRegistrations();
        $oprecNumbers = $this->seedOprecApplicants();

        $this->command->info('ScanTestSeeder done.');
        $this->command->info('  EVENT registrations (20, Accepted): '.implode(', ', $eventCodes));
        $this->command->info('  OPREC applications (20, Interview/Scheduled): '.implode(', ', $oprecNumbers));
        $this->command->info('  QR folders: storage/app/scan-test/event/ ('.count($eventCodes).' PNG), storage/app/scan-test/oprec/ ('.count($oprecNumbers).' PNG)');
        $this->command->info('  Test logins (password: password): scan-test-event-01@example.test … scan-test-event-20@example.test');

        $this->sendQrDigests($eventCodes, $oprecNumbers);
    }

    /**
     * Buang PNG lama lebih dulu: registration code dibuat acak per run, jadi tanpa
     * ini file dari run sebelumnya menumpuk (nama file berbeda walau barisnya sama).
     */
    private function purgeStaleQrFiles(): void
    {
        foreach (['event', 'oprec'] as $kind) {
            $dir = storage_path('app/scan-test/'.$kind);

            if (File::isDirectory($dir)) {
                File::cleanDirectory($dir);
            }
        }
    }

    /**
     * Kirim digest QR + daftar kodenya ke inbox uji. Hanya jalan di APP_ENV=local dan
     * hanya bila MAIL_TEST_REDIRECT terisi; AppServiceProvider::boot() memang sudah
     * mengarahkan semua email lokal ke alamat itu, jadi tidak ada email nyata ke peserta.
     *
     * @param  list<string>  $eventCodes
     * @param  list<string>  $oprecNumbers
     */
    private function sendQrDigests(array $eventCodes, array $oprecNumbers): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $recipient = config('mail.test_redirect');

        if (! is_string($recipient) || $recipient === '') {
            $this->command->warn('ScanTestSeeder: MAIL_TEST_REDIRECT kosong, digest QR tidak dikirim.');

            return;
        }

        $digests = [
            ['kind' => 'event', 'label' => 'Event', 'rows' => $this->eventDigestRows($eventCodes)],
            ['kind' => 'oprec', 'label' => 'OpRec', 'rows' => $this->oprecDigestRows($oprecNumbers)],
        ];

        foreach ($digests as $digest) {
            $attachments = $this->qrAttachments($digest['kind']);

            if ($attachments === [] && $digest['rows'] === []) {
                continue;
            }

            Mail::to($recipient)->send(new ScanTestQrMail($digest['label'], $digest['rows'], $attachments));

            $this->command->info('  QR digest '.$digest['label'].': '.count($attachments).' lampiran, '.count($digest['rows']).' baris daftar -> '.$recipient);
        }
    }

    /**
     * @param  list<string>  $codes
     * @return array<int, array{code:string,name:string,kind:string,context:string}>
     */
    private function eventDigestRows(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return FormAnswer::query()
            ->whereIn('registration_code', $codes)
            ->with(['form.event', 'user'])
            ->get()
            ->sortBy('registration_code')
            ->map(static fn (FormAnswer $answer): array => [
                'code' => (string) $answer->registration_code,
                'name' => $answer->user?->name ?? 'Tanpa nama',
                'kind' => 'event',
                'context' => (string) ($answer->form?->event?->title ?? '-'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $numbers
     * @return array<int, array{code:string,name:string,kind:string,context:string}>
     */
    private function oprecDigestRows(array $numbers): array
    {
        if ($numbers === []) {
            return [];
        }

        return RecruitmentApplication::query()
            ->whereIn('registration_number', $numbers)
            ->with(['period:id,name', 'primaryDivision:id,name'])
            ->orderBy('registration_number')
            ->get()
            ->map(static function (RecruitmentApplication $application): array {
                $context = trim(
                    ($application->period?->name ?? '').' · '.($application->primaryDivision?->name ?? ''),
                    ' ·'
                );

                return [
                    'code' => (string) $application->registration_number,
                    'name' => (string) $application->full_name,
                    'kind' => 'oprec',
                    'context' => $context !== '' ? $context : '-',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string> nama berkas => isi biner PNG
     */
    private function qrAttachments(string $kind): array
    {
        $dir = storage_path('app/scan-test/'.$kind);

        if (! File::isDirectory($dir)) {
            return [];
        }

        $attachments = [];

        foreach (File::files($dir) as $file) {
            $attachments[$file->getFilename()] = (string) file_get_contents($file->getPathname());
        }

        ksort($attachments);

        return $attachments;
    }

    /**
     * @return list<string> registration codes (usable in manual scan input)
     */
    private function seedEventRegistrations(): array
    {
        $forms = $this->scannableRegistrationForms();

        if ($forms === []) {
            $this->command->warn('ScanTestSeeder: no Published event with a Registration Form found, skipping EVENT part.');

            return [];
        }

        $codeIssuer = app(RegistrationCodeIssuer::class);
        $qr = app(RegistrationQrPngGenerator::class);
        $dir = storage_path('app/scan-test/event');
        File::ensureDirectoryExists($dir);

        $codes = [];
        $created = 0;

        for ($i = 1; $i <= 20; $i++) {
            $form = $forms[($i - 1) % count($forms)];
            $tag = sprintf('%02d', $i);

            $user = User::query()->firstOrCreate(
                ['email' => "scan-test-event-{$tag}@example.test"],
                ['name' => "Scan Test Event {$tag}", 'password' => 'password'],
            );
            if (! $user->hasRole('member')) {
                $user->syncRoles(['member']);
            }

            $answer = FormAnswer::query()->firstOrNew([
                'form_id' => $form->id,
                'user_id' => $user->id,
            ]);

            if (! $answer->exists) {
                $answer->fill([
                    'answers' => [
                        'full_name' => $user->name,
                        'nim' => sprintf('A11.2026.8%04d', $i),
                        'note' => 'SCAN-TEST',
                    ],
                    'review_status' => FormAnswerReviewStatus::Accepted,
                    'reviewed_at' => now(),
                    'registration_code' => $this->uniqueEventCode($codeIssuer),
                ]);
                $answer->save();
                $created++;
            } else {
                $dirty = false;
                if ($answer->review_status !== FormAnswerReviewStatus::Accepted) {
                    $answer->review_status = FormAnswerReviewStatus::Accepted;
                    $answer->reviewed_at = now();
                    $dirty = true;
                }
                if (empty($answer->registration_code)) {
                    $answer->registration_code = $this->uniqueEventCode($codeIssuer);
                    $dirty = true;
                }
                if ($dirty) {
                    $answer->save();
                }
            }

            File::put($dir.'/event-'.$answer->registration_code.'.png', $qr->pngForSubmission($answer->id));
            $codes[] = (string) $answer->registration_code;
        }

        $this->command->info("  EVENT: {$created} created, ".(20 - $created).' reused across '.count($forms).' Published events.');

        return $codes;
    }

    /**
     * @return list<Form>
     */
    private function scannableRegistrationForms(): array
    {
        $forms = [];
        $events = Event::query()->where('status', EventStatus::Published)->orderBy('title')->get();

        foreach ($events as $event) {
            $form = Form::query()
                ->where('event_id', $event->id)
                ->where('title', 'Registration Form')
                ->first();

            if ($form === null) {
                continue;
            }

            $dirty = false;

            $visible = $form->visible_for instanceof \Illuminate\Support\Collection
                ? $form->visible_for->all()
                : (array) $form->visible_for;
            if (! in_array(EventFormVisibility::Public, $visible, true)) {
                $visible[] = EventFormVisibility::Public;
                $form->visible_for = $visible;
                $dirty = true;
            }

            if ($form->closed_at === null || $form->closed_at->isPast()) {
                $form->closed_at = now()->addDays(30);
                $dirty = true;
            }

            if ($dirty) {
                $form->save();
            }

            $forms[] = $form;
        }

        return $forms;
    }

    private function uniqueEventCode(RegistrationCodeIssuer $issuer): string
    {
        do {
            $code = $issuer->generate();
        } while (FormAnswer::query()->where('registration_code', $code)->exists());

        return $code;
    }

    /**
     * @return list<string> registration numbers (usable in manual scan input)
     */
    private function seedOprecApplicants(): array
    {
        $period = RecruitmentPeriod::query()->firstOrCreate(
            ['slug' => 'scan-test-oprec-2026'],
            [
                'name' => 'Scan Test OpRec 2026',
                'status' => RecruitmentPeriodStatus::Open,
                'description' => 'TEMPORARY period for global-scan testing. Safe to delete.',
                'registration_opens_at' => now()->subWeek(),
                'registration_closes_at' => now()->addMonth(),
                'interview_starts_at' => today()->subDays(7)->toDateString(),
                'interview_ends_at' => today()->addDays(14)->toDateString(),
                'finalization_deadline_at' => today()->addDays(30)->toDateString(),
            ],
        );

        $period->status = RecruitmentPeriodStatus::Open;
        $period->registration_opens_at = now()->subWeek();
        $period->registration_closes_at = now()->addMonth();
        $period->interview_starts_at = today()->subDays(7)->toDateString();
        $period->interview_ends_at = today()->addDays(14)->toDateString();
        if ($period->isDirty()) {
            $period->save();
        }

        $divisions = RecruitmentDivision::query()
            ->whereIn('code', ['programming', 'medcrev', 'network', 'data'])
            ->orderBy('sort_order')
            ->get();

        if ($divisions->isEmpty()) {
            $this->command->warn('ScanTestSeeder: no recruitment divisions found, skipping OPREC part.');

            return [];
        }

        $qr = app(RecruitmentQrPngGenerator::class);
        $dir = storage_path('app/scan-test/oprec');
        File::ensureDirectoryExists($dir);

        $numbers = [];
        $createdApps = 0;
        $createdInterviews = 0;
        $n = 0;
        $sessionDate = today()->addDay();

        foreach ($divisions as $division) {
            $interviewer = User::query()->firstOrCreate(
                ['email' => "scan-test-interviewer-{$division->code}@example.test"],
                ['name' => "Scan Test Interviewer {$division->code}", 'password' => 'password'],
            );
            if (! $interviewer->hasRole('recruitment-interviewer')) {
                $interviewer->syncRoles(['recruitment-interviewer']);
            }
            RecruitmentInterviewerDivision::query()->firstOrCreate([
                'user_id' => $interviewer->id,
                'recruitment_division_id' => $division->id,
            ]);

            $session = RecruitmentInterviewSession::query()->firstOrCreate(
                [
                    'recruitment_period_id' => $period->id,
                    'recruitment_division_id' => $division->id,
                ],
                [
                    'session_date' => $sessionDate->toDateString(),
                    'starts_at' => '09:00:00',
                    'ends_at' => '12:00:00',
                    'location' => 'Gedung H Lt. 4 (Scan Test)',
                    'room' => 'R-'.strtoupper((string) $division->code),
                    'is_active' => true,
                ],
            );
            if (! $session->is_active || $session->session_date->lt(today())) {
                $session->update([
                    'session_date' => $sessionDate->toDateString(),
                    'is_active' => true,
                ]);
            }
            $session->refresh();

            for ($k = 1; $k <= 5; $k++) {
                $n++;
                $tag = sprintf('%02d', $n);

                $application = RecruitmentApplication::query()->firstOrCreate(
                    ['registration_number' => sprintf('OPREC-%d-9%04d', now()->year, $n)],
                    [
                        'recruitment_period_id' => $period->id,
                        'tracking_token_hash' => Hash::make('scan-test-token-'.$n),
                        'full_name' => "Scan Test Oprec {$tag}",
                        'nim' => sprintf('A11.2026.9%04d', $n),
                        'semester' => ($n % 3) + 1,
                        'phone' => sprintf('0812900%04d', $n),
                        'personal_email' => "scan-test-oprec-{$tag}@example.test",
                        'student_email' => "scan-test-oprec-{$tag}@students.dinus.ac.id",
                        'instagram_username' => "scantest.oprec.{$tag}",
                        'primary_division_id' => $division->id,
                        'secondary_division_id' => null,
                        'stage' => ApplicationStage::Interview,
                        'result' => ApplicationResult::Pending,
                        'is_verified' => true,
                        'revision_required' => false,
                        'submitted_at' => now(),
                    ],
                );
                if ($application->wasRecentlyCreated) {
                    $createdApps++;
                } elseif (
                    $application->stage !== ApplicationStage::Interview
                    || $application->recruitment_period_id !== $period->id
                    || $application->primary_division_id !== $division->id
                ) {
                    $application->update([
                        'recruitment_period_id' => $period->id,
                        'primary_division_id' => $division->id,
                        'stage' => ApplicationStage::Interview,
                    ]);
                }

                // Direct insert (not InterviewSchedulingService): the service
                // dispatches applicant/interviewer notification emails, which a
                // scan-test seed must not trigger. Same end state: Scheduled.
                $scheduledAt = Carbon::parse(
                    $session->session_date->format('Y-m-d').' '.substr((string) $session->starts_at, 0, 5),
                    config('app.timezone')
                )->addMinutes(($k - 1) * 15);

                $interview = RecruitmentInterview::query()->firstOrCreate(
                    ['recruitment_application_id' => $application->id],
                    [
                        'recruitment_interview_session_id' => $session->id,
                        'interviewer_id' => $interviewer->id,
                        'scheduled_at' => $scheduledAt,
                        'location' => (string) $session->location,
                        'room' => (string) $session->room,
                        'status' => InterviewStatus::Scheduled,
                    ],
                );
                if ($interview->wasRecentlyCreated) {
                    $createdInterviews++;
                }

                File::put($dir.'/oprec-'.$application->registration_number.'.png', $qr->pngForApplication($application->id));
                $numbers[] = $application->registration_number;
            }
        }

        $this->command->info("  OPREC: {$createdApps} applications created, {$createdInterviews} interviews created (".count($numbers).' total, 5 per division).');

        return $numbers;
    }
}
