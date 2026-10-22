<?php

namespace App\Console\Commands\Stress;

use Database\Seeders\Stress\StressBroadcastSeeder;
use Database\Seeders\Stress\StressDatasetSeeder;
use Database\Seeders\Stress\StressEventDomainSeeder;
use Database\Seeders\Stress\StressRecruitmentSeeder;
use Database\Seeders\Stress\StressUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isi dataset stress 1 juta baris per tabel domain (non-produksi) dan tulis manifest untuk harness k6.
 * Tanpa `--force` command berhenti bila tabel domain sudah terisi; `--force` menghapus isinya lebih dulu.
 */
final class SeedStressDatasetCommand extends Command
{
    /** Batas atas scale untuk mencegah salah ketik yang menghabiskan disk. */
    private const MAX_SCALE = 5_000_000;

    /** Nilai contoh untuk sessionStorage 'scan-desk-id' di halaman scan. */
    private const SCAN_DESK_ID = 'stress-desk-1';

    /** Buffer pool selama seed: 128 MB default MySQL membuat insert 1 juta baris I/O bound (lihat changelog). */
    private const SEED_BUFFER_POOL_BYTES = 2_147_483_648;

    /** Umur lock anti-seed-berbarengan; lebih lama dari durasi seed realistis. */
    private const LOCK_TTL_SECONDS = 21_600;

    /** Tabel pendukung yang wajib ada agar role admin stress punya permission. */
    private const REQUIRED_AUX_TABLES = ['roles', 'permissions', 'model_has_roles'];

    /** Tabel transaksi recruitment yang ikut direset agar tidak ada baris yatim saat seed ulang. */
    private const RECRUITMENT_TABLES = [
        'recruitment_activity_logs',
        'recruitment_feedbacks',
        'recruitment_final_decisions',
        'recruitment_evaluations',
        'recruitment_attendances',
        'recruitment_interviews',
        'recruitment_interview_sessions',
        'recruitment_correction_requests',
        'recruitment_screenings',
        'recruitment_documents',
        'recruitment_applications',
        'recruitment_registration_sequences',
        'recruitment_email_templates',
        'recruitment_interviewer_divisions',
        'recruitment_divisions',
        'recruitment_periods',
    ];

    protected $signature = 'stress:seed
        {--scale='.StressDatasetSeeder::DEFAULT_SCALE.' : Jumlah baris per tabel domain}
        {--force : Hapus isi tabel domain + recruitment lebih dulu (destruktif, non-produksi)}
        {--no-tune : Lewati tuning MySQL sementara (flush=2, sync_binlog=0, FK/unique check off)}';

    protected $description = 'Seed dataset stress 1 juta baris/tabel domain + manifest (non-produksi)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('stress:seed dilarang di production.');

            return self::FAILURE;
        }

        $lock = Cache::lock('stress:seed', self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->error('Seed stress lain masih berjalan. Tunggu sampai selesai, atau hentikan dulu proses lama.');
            $this->line('Dua seed serentak memakai UUID deterministik yang sama sehingga saling menabrak (Duplicate entry ... PRIMARY).');
            $this->line('Bila proses lama sudah mati: php artisan tinker --execute="Cache::lock(\'stress:seed\')->forceRelease();"');

            return self::FAILURE;
        }

        app()->terminating(static fn () => $lock->release());
        $this->releaseLockOnSignal($lock);

        $scale = $this->resolveScale();

        if ($scale === null) {
            return self::FAILURE;
        }

        $missingTables = $this->missingTables([...StressDatasetSeeder::DOMAIN_TABLES, ...self::REQUIRED_AUX_TABLES]);

        if ($missingTables !== []) {
            $this->error('Tabel belum ada: '.implode(', ', $missingTables));
            $this->line('Jalankan `php artisan migrate` lebih dulu.');

            return self::FAILURE;
        }

        $filledTables = $this->filledDomainTables();

        if ($filledTables !== []) {
            if (! (bool) $this->option('force')) {
                $this->error('Tabel domain sudah terisi: '.implode(', ', $filledTables));
                $this->line('Jalankan ulang dengan --force untuk menghapus isinya (destruktif).');

                return self::FAILURE;
            }

            $this->warn('--force: menghapus seluruh isi tabel '.implode(', ', $filledTables));
            $this->warn('Tabel recruitment_* ikut direset agar tidak ada baris yatim setelah seed ulang.');
            $this->resetDomainTables();
        }

        $restoreMysql = $this->tuneForBulkInsert();
        $startedAt = microtime(true);

        try {
            $this->info('1/4 user + role');
            $this->runSeeder(StressUserSeeder::class, $scale);

            $this->info('2/4 recruitment');
            if (class_exists(StressRecruitmentSeeder::class)) {
                $this->runSeeder(StressRecruitmentSeeder::class, $scale);
            } else {
                $this->warn('StressRecruitmentSeeder belum tersedia; lane recruitment dilewati.');
            }

            $this->info('3/4 event, form, jawaban, kehadiran, email log');
            $this->runSeeder(StressEventDomainSeeder::class, $scale);

            $this->info('4/4 dataset & broadcast');
            $this->runSeeder(StressBroadcastSeeder::class, $scale);
        } finally {
            $restoreMysql();
        }

        $manifestPath = StressDatasetSeeder::writeManifest($this->manifest($scale));

        $this->newLine();
        $this->info(sprintf(
            'Selesai: %s tabel domain × %s baris + tabel recruitment dalam %.1f detik.',
            number_format(count(StressDatasetSeeder::DOMAIN_TABLES)),
            number_format($scale),
            microtime(true) - $startedAt
        ));
        $this->line('Manifest: '.$manifestPath);
        $this->line('Login: '.StressUserSeeder::adminEmail().' / '.StressUserSeeder::DEMO_PASSWORD);
        $this->line('Event: '.StressEventDomainSeeder::eventSlug(1));

        return self::SUCCESS;
    }

    /**
     * Lepas lock saat Ctrl-C/SIGTERM supaya seed berikutnya tidak tertahan TTL 6 jam.
     */
    private function releaseLockOnSignal(Lock $lock): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, static function () use ($lock): void {
                $lock->release();
                exit(130);
            });
        }
    }

    /**
     * Paksa durability MySQL turun selama seed (flush per commit + binlog sync) dan matikan
     * pemeriksaan FK/unique agar insert massal tidak I/O bound. Semua nilai dipulihkan lewat closure.
     */
    private function tuneForBulkInsert(): callable
    {
        if ((bool) $this->option('no-tune') || DB::getDriverName() !== 'mysql') {
            return static function (): void {
            };
        }

        /** @var array<string, string|null> $before */
        $before = [];

        foreach (['innodb_flush_log_at_trx_commit', 'sync_binlog', 'innodb_buffer_pool_size'] as $variable) {
            $row = (array) DB::selectOne("SHOW GLOBAL VARIABLES LIKE '{$variable}'");
            $before[$variable] = isset($row['Value']) ? (string) $row['Value'] : null;
        }

        DB::statement('SET GLOBAL innodb_flush_log_at_trx_commit = 2');
        DB::statement('SET GLOBAL sync_binlog = 0');
        DB::statement('SET SESSION foreign_key_checks = 0');
        DB::statement('SET SESSION unique_checks = 0');

        try {
            DB::statement('SET GLOBAL innodb_buffer_pool_size = '.self::SEED_BUFFER_POOL_BYTES);
        } catch (\Throwable $error) {
            $this->warn('Buffer pool tidak bisa dinaikkan, seed tetap jalan lebih lambat: '.$error->getMessage());
        }

        $this->warn('Tuning seed aktif: flush_log_at_trx_commit=2, sync_binlog=0, foreign_key_checks=0, unique_checks=0, buffer_pool=2G (dipulihkan setelah selesai).');

        return static function () use ($before): void {
            DB::statement('SET SESSION foreign_key_checks = 1');
            DB::statement('SET SESSION unique_checks = 1');

            $tuned = [
                'innodb_flush_log_at_trx_commit' => '2',
                'sync_binlog' => '0',
                'innodb_buffer_pool_size' => (string) self::SEED_BUFFER_POOL_BYTES,
            ];

            foreach ($before as $variable => $value) {
                if ($value === null) {
                    continue;
                }

                $row = (array) DB::selectOne("SHOW GLOBAL VARIABLES LIKE '{$variable}'");
                $current = isset($row['Value']) ? (string) $row['Value'] : null;

                // Hanya pulihkan bila nilainya masih nilai yang kita pasang; perubahan pihak lain jangan ditimpa.
                if ($current !== $tuned[$variable]) {
                    continue;
                }

                DB::statement("SET GLOBAL {$variable} = {$value}");
            }
        };
    }

    /**
     * Jalankan seeder stress dengan skala tertentu lewat API Seeder (Command::call hanya menerima nama command artisan).
     */
    private function runSeeder(string $seederClass, int $scale): void
    {
        /** @var \Illuminate\Database\Seeder $seeder */
        $seeder = $this->laravel->make($seederClass);
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->run($scale);
    }

    /**
     * Validasi --scale sebagai bilangan bulat dalam rentang aman.
     */
    private function resolveScale(): ?int
    {
        $raw = (string) $this->option('scale');

        if (! ctype_digit($raw) || (int) $raw < 1 || (int) $raw > self::MAX_SCALE) {
            $this->error('--scale harus bilangan bulat 1..'.number_format(self::MAX_SCALE).'.');

            return null;
        }

        return (int) $raw;
    }

    /**
     * Tabel yang belum ada (domain + pendukung role).
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function missingTables(array $tables): array
    {
        return array_values(array_filter(
            $tables,
            static fn (string $table): bool => ! Schema::hasTable($table)
        ));
    }

    /**
     * Tabel domain yang sudah berisi minimal satu baris.
     *
     * @return list<string>
     */
    private function filledDomainTables(): array
    {
        return array_values(array_filter(
            $this->resettableTables(),
            static fn (string $table): bool => DB::table($table)->exists()
        ));
    }

    /**
     * Tabel yang ikut direset/diperiksa: transaksi recruitment + domain event (anak → induk).
     *
     * @return list<string>
     */
    private function resettableTables(): array
    {
        return [...self::RECRUITMENT_TABLES, ...StressDatasetSeeder::DOMAIN_TABLES];
    }

    /**
     * Kosongkan tabel domain + recruitment tanpa memicu pemeriksaan FK di MySQL.
     */
    private function resetDomainTables(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach ($this->resettableTables() as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * Manifest id & kredensial contoh; `targets` dipakai scripts/stress/generate-targets.php.
     *
     * @return array<string, mixed>
     */
    private function manifest(int $scale): array
    {
        $eventId = StressDatasetSeeder::uuidFor('event', 1);
        $formId = StressDatasetSeeder::uuidFor('form', 1);

        return [
            'admin_email' => StressUserSeeder::adminEmail(),
            'admin_password' => StressUserSeeder::DEMO_PASSWORD,
            'event_slug' => StressEventDomainSeeder::eventSlug(1),
            'event_id' => $eventId,
            'form_id' => $formId,
            'form_answer_id' => StressDatasetSeeder::uuidFor('form_answer', 1),
            'registration_code' => StressEventDomainSeeder::registrationCode(1),
            'scan_desk_id' => self::SCAN_DESK_ID,
            'user_id' => StressDatasetSeeder::uuidFor('user', 1),
            'broadcast_id' => StressDatasetSeeder::uuidFor('email_broadcast', 1),
            'scale' => $scale,
            'generated_at' => now()->toIso8601String(),
            'targets' => [
                ['name' => 'dashboard.events.show', 'uri' => '/admin/events/'.$eventId],
                ['name' => 'dashboard.events.forms.submissions', 'uri' => '/admin/events/'.$eventId.'/forms/'.$formId.'/submissions'],
                ['name' => 'dashboard.scan.index', 'uri' => '/admin/scan'],
                ['name' => 'dashboard.users.index', 'uri' => '/admin/users'],
            ],
        ];
    }
}
