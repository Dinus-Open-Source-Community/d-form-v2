<?php

namespace Tests\Feature\E2e;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\Recruitment\RecruitmentDivision;
use App\Support\E2e\FixtureIdentity;
use App\Support\E2e\FixtureJournal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kontrak `e2e:fixture` yang dipakai spec Playwright.
 *
 * Section recruitment, acceptedAnswers, dan broadcasts harus mengembalikan id
 * yang cocok dengan baris DB ber-marker, dan `e2e:cleanup` harus menyisakan
 * nol baris marker tanpa menghapus baris di luar marker.
 */
final class FixtureCommandTest extends TestCase
{
    use RefreshDatabase;

    private const RUN_ID = '20260102T030405-abcdef';

    public function test_it_creates_marker_owned_recruitment_accepted_answer_and_broadcast(): void
    {
        RecruitmentDivision::factory()->create([
            'code' => 'e2e-div',
            'name' => 'E2E Division',
            'is_active' => true,
        ]);

        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-fixture-test');
        $result = $this->runFixture($identity, [
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'title' => 'E2E fixture event',
            'forms' => [
                [
                    'title' => 'Form 1',
                    'fields' => [
                        ['label' => 'Nama', 'name' => 'name', 'input_type' => 'input', 'required' => true],
                    ],
                    'submissions' => 0,
                ],
            ],
            'acceptedAnswers' => [
                [
                    'formIndex' => 0,
                    'registrationCode' => 'E2E-CODE-1',
                    'answers' => ['name' => 'E2E Attendee'],
                    'invitedEmail' => 'attendee@e2e.invalid',
                ],
            ],
            'recruitment' => [
                'period' => ['name' => 'E2E recruitment', 'status' => 'closed'],
                'application' => [
                    'registrationNumber' => 'OPREC-E2E-1',
                    'trackingToken' => 'tracking-token-e2e-0123456789abcdef',
                    'fullName' => 'E2E Applicant',
                    'stage' => 'screening',
                    'result' => 'pending',
                ],
            ],
            'broadcasts' => [
                [
                    'name' => 'E2E broadcast',
                    'subject' => 'E2E subject',
                    'recipients' => [['name' => 'Penerima', 'email' => 'recipient@e2e.invalid']],
                ],
            ],
        ]);

        $this->assertSame($identity->marker, $result['marker']);
        $this->assertSame(1, DB::table('users')->where('email', $identity->email())->count());
        $this->assertSame(1, DB::table('events')->where('slug', $identity->slug())->count());

        $accepted = $result['acceptedAnswers'][0];
        $answer = DB::table('form_answers')->where('id', $accepted['answerId'])->first();
        $this->assertNotNull($answer);
        $this->assertSame('accepted', $answer->review_status);
        $this->assertSame('E2E-CODE-1', $answer->registration_code);
        $this->assertSame('attendee@e2e.invalid', $answer->invited_email);
        $this->assertNull($answer->user_id);
        $this->assertSame(0, DB::table('event_attendances')->where('form_answer_id', $accepted['answerId'])->count());

        $recruitment = $result['recruitment'];
        $this->assertSame('OPREC-E2E-1', $recruitment['registrationNumber']);
        $application = DB::table('recruitment_applications')->where('id', $recruitment['applicationId'])->first();
        $this->assertNotNull($application);
        $this->assertTrue(Hash::check('tracking-token-e2e-0123456789abcdef', $application->tracking_token_hash));
        $this->assertSame(
            $identity->suffixed('E2E recruitment'),
            DB::table('recruitment_periods')->where('id', $recruitment['periodId'])->value('name'),
        );
        $this->assertSame(
            1,
            DB::table('recruitment_documents')->where('recruitment_application_id', $recruitment['applicationId'])->count(),
        );

        $broadcast = $result['broadcasts'][0];
        $this->assertSame(
            1,
            DB::table('email_broadcast_recipients')->where('broadcast_id', $broadcast['id'])->count(),
        );

        $this->assertSame(0, Artisan::call('e2e:cleanup', [
            '--marker' => $identity->marker,
            '--slug' => $identity->slug(),
        ]));

        $counts = $this->rowCounts($identity->marker);
        $this->assertSame(0, array_sum($counts), 'cleanup menyisakan baris: '.json_encode($counts));
        $this->assertSame(0, DB::table('recruitment_periods')->where('id', $recruitment['periodId'])->count());
        $this->assertSame(0, DB::table('recruitment_applications')->where('id', $recruitment['applicationId'])->count());
        $this->assertSame(0, DB::table('email_broadcasts')->where('id', $broadcast['id'])->count());
        $this->assertSame(0, DB::table('form_answers')->where('id', $accepted['answerId'])->count());
    }

    public function test_accepted_answer_can_create_attendance_on_demand(): void
    {
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-attendance');
        $result = $this->runFixture($identity, [
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'title' => 'E2E fixture event',
            'forms' => [
                [
                    'title' => 'Form 1',
                    'fields' => [
                        ['label' => 'Nama', 'name' => 'name', 'input_type' => 'input', 'required' => true],
                    ],
                    'submissions' => 0,
                ],
            ],
            'acceptedAnswers' => [
                [
                    'formIndex' => 0,
                    'registrationCode' => 'E2E-CODE-ATTENDANCE',
                    'answers' => ['name' => 'E2E Attendee'],
                    'invitedEmail' => 'attendee@e2e.invalid',
                    'attendance' => true,
                ],
            ],
        ]);

        $accepted = $result['acceptedAnswers'][0];
        $this->assertNotNull($accepted['attendanceId']);
        $this->assertSame(
            1,
            DB::table('event_attendances')->where('form_answer_id', $accepted['answerId'])->count(),
        );

        $this->assertSame(0, Artisan::call('e2e:cleanup', [
            '--marker' => $identity->marker,
            '--slug' => $identity->slug(),
        ]));
        $this->assertSame(0, array_sum($this->rowCounts($identity->marker)));
    }

    public function test_invalid_payload_rolls_back_rows_created_before_failure(): void
    {
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-invalid-payload');

        try {
            $this->runFixture($identity, [
                'marker' => $identity->marker,
                'slug' => $identity->slug(),
                'title' => 'E2E fixture event',
                'forms' => [
                    [
                        'title' => 'Form 1',
                        'fields' => [
                            ['label' => 'Nama', 'name' => 'name', 'input_type' => 'input', 'required' => true],
                        ],
                        'submissions' => 0,
                    ],
                ],
                'acceptedAnswers' => [
                    ['formIndex' => 9, 'registrationCode' => 'E2E-CODE-INVALID'],
                ],
            ]);
            $this->fail('Payload dengan formIndex di luar form seharusnya gagal.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('formIndex', $exception->getMessage());
        }

        $counts = $this->rowCounts($identity->marker);
        $this->assertSame(0, array_sum($counts), 'rollback menyisakan baris: '.json_encode($counts));
        $this->assertSame(0, DB::table('users')->where('email', $identity->email())->count());
    }

    public function test_payload_option_is_rejected_outside_testing_environment(): void
    {
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-inline-payload');
        $this->app['env'] = 'local';

        try {
            Artisan::call('e2e:fixture', [
                '--marker' => $identity->marker,
                '--payload' => json_encode(['marker' => $identity->marker], JSON_THROW_ON_ERROR),
            ]);
            $this->fail('--payload di luar environment testing seharusnya ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('--payload is test-only', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, DB::table('users')->where('email', $identity->email())->count());
    }

    public function test_production_guard_blocks_fixture_without_creating_rows(): void
    {
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-production-guard');
        $this->app['env'] = 'production';

        try {
            $exit = Artisan::call('e2e:fixture', ['--marker' => $identity->marker]);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(1, $exit);
        $this->assertSame(0, DB::table('users')->where('email', $identity->email())->count());
    }

    public function test_accepted_answer_registration_code_cannot_adopt_foreign_answer(): void
    {
        RecruitmentDivision::factory()->create(['code' => 'e2e-div', 'name' => 'E2E', 'is_active' => true]);
        $foreignAnswer = FormAnswer::factory()->create([
            'form_id' => Form::factory()->create()->id,
            'registration_code' => 'E2E-FOREIGN-CODE',
        ]);
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-foreign-code');

        try {
            $this->runFixture($identity, [
                'marker' => $identity->marker,
                'slug' => $identity->slug(),
                'title' => 'E2E fixture event',
                'forms' => [
                    [
                        'title' => 'Form 1',
                        'fields' => [
                            ['label' => 'Nama', 'name' => 'name', 'input_type' => 'input', 'required' => true],
                        ],
                        'submissions' => 0,
                    ],
                ],
                'acceptedAnswers' => [
                    ['formIndex' => 0, 'registrationCode' => 'E2E-FOREIGN-CODE'],
                ],
            ]);
            $this->fail('Registration code milik form lain seharusnya ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('already exists on another form', $exception->getMessage());
        }

        $this->assertSame(
            0,
            DB::table('event_attendances')->where('form_answer_id', $foreignAnswer->id)->count(),
        );
        $this->assertSame(0, array_sum($this->rowCounts($identity->marker)));
        $this->assertSame(1, DB::table('form_answers')->where('id', $foreignAnswer->id)->count());
    }

    public function test_tracking_token_requires_entropy_or_is_generated(): void
    {
        RecruitmentDivision::factory()->create(['code' => 'e2e-div', 'name' => 'E2E', 'is_active' => true]);
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-short-token');

        try {
            $this->runFixture($identity, $this->recruitmentPayload($identity, 'short-token'));
            $this->fail('Token tracking di bawah 32 karakter seharusnya ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('at least 32 characters', $exception->getMessage());
        }

        $this->assertSame(0, array_sum($this->rowCounts($identity->marker)));

        $generatedIdentity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-generated-token');
        $result = $this->runFixture($generatedIdentity, $this->recruitmentPayload($generatedIdentity, null));
        $token = $result['recruitment']['trackingToken'];
        $this->assertIsString($token);
        $this->assertSame(40, mb_strlen($token));

        $application = DB::table('recruitment_applications')
            ->where('id', $result['recruitment']['applicationId'])
            ->first();
        $this->assertNotNull($application);
        $this->assertTrue(Hash::check($token, $application->tracking_token_hash));

        $this->assertSame(0, Artisan::call('e2e:cleanup', [
            '--marker' => $generatedIdentity->marker,
            '--slug' => $generatedIdentity->slug(),
        ]));
        $this->assertSame(0, array_sum($this->rowCounts($generatedIdentity->marker)));
    }

    public function test_row_ownership_reports_ok_after_fixture_cleanup(): void
    {
        $runId = '20260102T030407-abcdef';
        $identity = FixtureIdentity::fromOwnerId($runId, 'owner-uuid-ownership');

        $this->runFixture($identity, [
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'title' => 'E2E fixture event',
            'forms' => [
                [
                    'title' => 'Form 1',
                    'fields' => [
                        ['label' => 'Nama', 'name' => 'name', 'input_type' => 'input', 'required' => true],
                    ],
                    'submissions' => 1,
                ],
            ],
            'acceptedAnswers' => [
                [
                    'formIndex' => 0,
                    'registrationCode' => 'E2E-CODE-OWNERSHIP',
                    'answers' => ['name' => 'E2E Attendee'],
                    'invitedEmail' => 'attendee@e2e.invalid',
                    'attendance' => true,
                ],
            ],
        ]);

        $this->assertSame(0, Artisan::call('e2e:cleanup', [
            '--marker' => $identity->marker,
            '--slug' => $identity->slug(),
        ]));

        $exit = Artisan::call('check:row-ownership', ['--run-id' => $runId, '--json' => true]);
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit);
        $this->assertTrue($report['ok']);
        $this->assertSame(0, $report['leftoverTotal']);
        $this->assertSame(0, $report['illegalTotal']);
        $this->assertSame(0, $report['crossRun']['violations']);
    }

    public function test_rollback_failure_keeps_journal_and_chains_payload_error(): void
    {
        $journal = new FixtureJournal();
        $journal->record('__e2e_missing_table__', 'missing-row');
        $cause = new \RuntimeException('payload failed');

        try {
            $journal->rollback($cause);
            $this->fail('Rollback ke tabel tidak ada seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('payload failed', $exception->getMessage());
            $this->assertSame($cause, $exception->getPrevious());
        }

        // Entri tidak boleh hilang sebelum delete sukses: percobaan kedua masih mencoba dan gagal.
        try {
            $journal->rollback($cause);
            $this->fail('Journal yang gagal rollback seharusnya tetap menyimpan entri.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_cleanup_removes_marker_owned_email_datasets(): void
    {
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-dataset');
        $datasetId = (string) Str::orderedUuid();
        $recipientId = (string) Str::orderedUuid();

        DB::table('email_datasets')->insert([
            'id' => $datasetId,
            'name' => $identity->suffixed('E2E dataset'),
            'source_type' => 'custom',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('email_dataset_recipients')->insert([
            'id' => $recipientId,
            'dataset_id' => $datasetId,
            'email' => 'dataset@e2e.invalid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runFixture($identity, [
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'title' => 'E2E fixture event',
            'forms' => [],
        ]);

        $this->assertSame(0, Artisan::call('e2e:cleanup', [
            '--marker' => $identity->marker,
            '--slug' => $identity->slug(),
        ]));

        $this->assertSame(0, DB::table('email_dataset_recipients')->where('id', $recipientId)->count());
        $this->assertSame(0, DB::table('email_datasets')->where('id', $datasetId)->count());
        $this->assertSame(0, array_sum($this->rowCounts($identity->marker)));
    }

    public function test_recruitment_reuses_same_fixture_application_without_mutating(): void
    {
        RecruitmentDivision::factory()->create(['code' => 'e2e-div', 'name' => 'E2E', 'is_active' => true]);
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-reuse-application');
        $token = 'reuse-token-0123456789abcdefghij';

        $first = $this->runFixture($identity, $this->recruitmentPayload($identity, $token));
        $hash = DB::table('recruitment_applications')
            ->where('id', $first['recruitment']['applicationId'])
            ->value('tracking_token_hash');

        $second = $this->runFixture($identity, $this->recruitmentPayload($identity, $token));
        $this->assertSame($first['recruitment']['applicationId'], $second['recruitment']['applicationId']);
        $this->assertSame(
            $hash,
            DB::table('recruitment_applications')
                ->where('id', $first['recruitment']['applicationId'])
                ->value('tracking_token_hash'),
        );

        try {
            $this->runFixture($identity, $this->recruitmentPayload($identity, $token, 'Foreign Applicant'));
            $this->fail('Aplikasi dengan identitas berbeda seharusnya tidak boleh dipakai ulang.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('different fixture identity', $exception->getMessage());
        }

        $this->assertSame(
            $hash,
            DB::table('recruitment_applications')
                ->where('id', $first['recruitment']['applicationId'])
                ->value('tracking_token_hash'),
        );
    }

    public function test_period_slug_collision_under_other_name_is_rejected(): void
    {
        RecruitmentDivision::factory()->create(['code' => 'e2e-div', 'name' => 'E2E', 'is_active' => true]);
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-period-slug');

        $this->runFixture($identity, $this->recruitmentPayload($identity, 'period-token-0123456789abcdefghij'));

        try {
            $this->runFixture($identity, $this->recruitmentPayload(
                $identity,
                'period-token-0123456789abcdefghij',
                null,
                'Other period name',
            ));
            $this->fail('Slug periode milik nama lain seharusnya ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('slug already belongs to a different period name', $exception->getMessage());
        }

        $this->assertSame(
            1,
            DB::table('recruitment_periods')->where('slug', $identity->slug())->count(),
        );
    }

    public function test_broadcast_status_requiring_schedule_is_rejected(): void
    {
        $identity = FixtureIdentity::fromOwnerId(self::RUN_ID, 'owner-uuid-broadcast-status');

        try {
            $this->runFixture($identity, [
                'marker' => $identity->marker,
                'slug' => $identity->slug(),
                'title' => 'E2E fixture event',
                'forms' => [],
                'broadcasts' => [
                    [
                        'name' => 'E2E scheduled broadcast',
                        'status' => 'scheduled',
                        'recipients' => [['email' => 'recipient@e2e.invalid']],
                    ],
                ],
            ]);
            $this->fail('Status broadcast scheduled tanpa jadwal seharusnya ditolak.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('only supports draft, cancelled, or failed', $exception->getMessage());
        }

        $this->assertSame(0, array_sum($this->rowCounts($identity->marker)));
    }

    /**
     * Payload recruitment minimum; `null` token membuat command memakai token acak.
     *
     * @return array<string, mixed>
     */
    private function recruitmentPayload(
        FixtureIdentity $identity,
        ?string $trackingToken,
        ?string $fullName = null,
        ?string $periodName = null,
    ): array {
        $application = [
            'registrationNumber' => 'OPREC-'.$identity->ownerKey,
            'fullName' => $fullName ?? 'E2E Applicant',
        ];

        if ($trackingToken !== null) {
            $application['trackingToken'] = $trackingToken;
        }

        return [
            'marker' => $identity->marker,
            'slug' => $identity->slug(),
            'title' => 'E2E fixture event',
            'forms' => [],
            'recruitment' => [
                'period' => ['name' => $periodName ?? 'E2E recruitment', 'status' => 'closed'],
                'application' => $application,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runFixture(FixtureIdentity $identity, array $payload): array
    {
        $exit = Artisan::call('e2e:fixture', [
            '--marker' => $identity->marker,
            '--payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $output = trim(Artisan::output());
        $this->assertSame(0, $exit, $output);

        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(string $marker): array
    {
        $exit = Artisan::call('e2e:count-rows', ['--marker' => $marker]);
        $this->assertSame(0, $exit);

        $decoded = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        /** @var array<string, int> $counts */
        $counts = $decoded['counts'];

        return $counts;
    }
}
