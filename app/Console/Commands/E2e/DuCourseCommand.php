<?php

namespace App\Console\Commands\E2e;

use App\Models\DuCourse;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Seed atau hapus baris cache `du_courses` untuk spec browser tanpa memanggil DU. */
final class DuCourseCommand extends Command
{
    protected $signature = 'e2e:du-course
        {--uid= : Uid kelas yang di-upsert}
        {--title= : Judul kelas yang di-upsert}
        {--clear= : Prefix uid yang barisnya dihapus}';

    protected $description = 'Seed or clear cached DU courses for e2e specs.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('e2e:du-course dilarang di production.');

            return self::FAILURE;
        }

        $clear = $this->option('clear');

        if ($clear !== null) {
            $prefix = (string) $clear;

            if ($this->option('uid') !== null || $this->option('title') !== null) {
                $this->error('--clear tidak boleh digabung dengan --uid atau --title.');

                return self::FAILURE;
            }

            if ($prefix === '' || ! str_starts_with($prefix, 'e2e-')) {
                $this->error('--clear wajib berisi prefix uid yang tidak kosong dan diawali "e2e-".');

                return self::FAILURE;
            }

            // Escape wildcard LIKE agar prefix tidak melebarkan cakupan hapus.
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $prefix);
            DuCourse::query()->where('uid', 'like', $escaped.'%')->delete();
            $this->line(json_encode(['ok' => true], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $uid = trim((string) ($this->option('uid') ?? ''));
        $title = trim((string) ($this->option('title') ?? ''));

        if ($uid === '' || $title === '') {
            $this->error('--uid dan --title wajib diisi bila --clear tidak dipakai.');

            return self::FAILURE;
        }

        $now = now();

        DuCourse::query()->upsert(
            [[
                'id' => (string) Str::uuid(),
                'uid' => $uid,
                'title' => $title,
                'is_active' => true,
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['uid'],
            ['title', 'is_active', 'last_synced_at', 'updated_at'],
        );

        $this->line(json_encode(['ok' => true], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
