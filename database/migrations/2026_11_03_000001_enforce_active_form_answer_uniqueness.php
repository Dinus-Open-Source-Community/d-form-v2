<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menegakkan keunikan submission aktif di level database untuk semua driver.
 *
 * Akar masalah: index parsial 2026_06_05/2026_06_11 memakai
 * `NOT (registration_role = 'member' AND ...)`. Untuk baris dengan
 * registration_role NULL, ekspresi itu bernilai NULL (bukan TRUE), sehingga baris
 * submission biasa tidak pernah masuk index. Di MySQL index parsial memang tidak
 * pernah dibuat. Hasilnya, di semua driver proteksi duplikat hanya bergantung pada
 * FormAccessGuard.
 *
 * Perbaikan:
 * - SQLite/PostgreSQL: index parsial dibuat ulang dengan predikat NULL-safe.
 * - MySQL: generated column VIRTUAL + unique index (MySQL tidak punya partial index;
 *   unique index mengizinkan banyak NULL, jadi baris tidak aktif tidak diblokir).
 */
return new class () extends Migration {
    private const USER_INDEX = 'form_answers_active_user_form_unique';

    private const GUEST_INDEX = 'form_answers_active_guest_email_form_unique';

    public function up(): void
    {
        $this->assertNoDuplicateGroups();

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->createMysqlGeneratedColumns();

            return;
        }

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::USER_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::GUEST_INDEX);

            DB::statement(
                'CREATE UNIQUE INDEX '.self::USER_INDEX.' '
                .'ON form_answers (user_id, form_id) '
                .'WHERE user_id IS NOT NULL '
                ."AND COALESCE(review_status, '') != 'rejected' "
                ."AND (COALESCE(registration_role, '') != 'member' "
                ."OR COALESCE(member_confirmation_status, '') NOT IN ('rejected', 'expired'))"
            );

            DB::statement(
                'CREATE UNIQUE INDEX '.self::GUEST_INDEX.' '
                .'ON form_answers (form_id, invited_email) '
                .'WHERE user_id IS NULL '
                .'AND invited_email IS NOT NULL '
                ."AND invited_email != '' "
                ."AND COALESCE(review_status, '') != 'rejected' "
                ."AND (COALESCE(registration_role, '') != 'member' "
                ."OR COALESCE(member_confirmation_status, '') NOT IN ('rejected', 'expired'))"
            );
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('form_answers', function (Blueprint $table): void {
                $table->dropUnique(self::USER_INDEX);
                $table->dropUnique(self::GUEST_INDEX);
                $table->dropColumn(['active_user_form_key', 'active_guest_email_form_key']);
            });

            return;
        }

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::USER_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::GUEST_INDEX);

            // Kembali ke definisi lama (predikat tidak NULL-safe) agar rollback setara
            // dengan keadaan sebelum migrasi ini.
            DB::statement(
                'CREATE UNIQUE INDEX '.self::USER_INDEX.' '
                .'ON form_answers (user_id, form_id) '
                ."WHERE COALESCE(review_status, '') != 'rejected' "
                ."AND NOT (registration_role = 'member' AND member_confirmation_status IN ('rejected', 'expired'))"
            );

            DB::statement(
                'CREATE UNIQUE INDEX '.self::GUEST_INDEX.' '
                .'ON form_answers (form_id, invited_email) '
                .'WHERE user_id IS NULL '
                .'AND invited_email IS NOT NULL '
                ."AND invited_email != '' "
                ."AND COALESCE(review_status, '') != 'rejected' "
                ."AND NOT (registration_role = 'member' AND member_confirmation_status IN ('rejected', 'expired'))"
            );
        }
    }

    /**
     * MySQL: satu kolom generated per jenis submission aktif. Nilai NULL berarti baris
     * tidak aktif; unique index MySQL mengabaikan NULL sehingga baris historis tetap boleh.
     */
    private function createMysqlGeneratedColumns(): void
    {
        DB::statement(
            'ALTER TABLE form_answers '
            .'ADD COLUMN active_user_form_key VARCHAR(73) '
            .'GENERATED ALWAYS AS ('
            .'CASE WHEN user_id IS NULL '
            ."OR COALESCE(review_status, '') = 'rejected' "
            ."OR (COALESCE(registration_role, '') = 'member' "
            ."AND COALESCE(member_confirmation_status, '') IN ('rejected', 'expired')) "
            ."THEN NULL ELSE CONCAT(user_id, ':', form_id) END"
            .') VIRTUAL, '
            .'ADD UNIQUE INDEX '.self::USER_INDEX.' (active_user_form_key)'
        );

        DB::statement(
            'ALTER TABLE form_answers '
            .'ADD COLUMN active_guest_email_form_key VARCHAR(292) '
            .'GENERATED ALWAYS AS ('
            .'CASE WHEN user_id IS NOT NULL '
            .'OR invited_email IS NULL '
            ."OR invited_email = '' "
            ."OR COALESCE(review_status, '') = 'rejected' "
            ."OR (COALESCE(registration_role, '') = 'member' "
            ."AND COALESCE(member_confirmation_status, '') IN ('rejected', 'expired')) "
            ."THEN NULL ELSE CONCAT(form_id, ':', invited_email) END"
            .') VIRTUAL, '
            .'ADD UNIQUE INDEX '.self::GUEST_INDEX.' (active_guest_email_form_key)'
        );
    }

    /**
     * Hentikan migrasi dengan pesan jelas bila data lama sudah berisi duplikat.
     * Baris tidak dihapus otomatis: pembersihan data produksi harus keputusan manusia.
     */
    private function assertNoDuplicateGroups(): void
    {
        $userDuplicates = $this->countDuplicateGroups(
            'user_id IS NOT NULL'
        );

        $guestDuplicates = $this->countDuplicateGroups(
            "user_id IS NULL AND invited_email IS NOT NULL AND invited_email != ''",
            'form_id, invited_email'
        );

        if ($userDuplicates === 0 && $guestDuplicates === 0) {
            return;
        }

        throw new RuntimeException(
            'form_answers contains active duplicate submissions '
            ."(user/form groups: {$userDuplicates}, form/invited_email groups: {$guestDuplicates}). "
            .'Resolve them before running this migration; the migration will not delete rows automatically.'
        );
    }

    private function countDuplicateGroups(string $scope, string $groupBy = 'user_id, form_id'): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS duplicate_groups FROM ('
            .'SELECT 1 FROM form_answers '
            ."WHERE {$scope} "
            ."AND COALESCE(review_status, '') != 'rejected' "
            ."AND (COALESCE(registration_role, '') != 'member' "
            ."OR COALESCE(member_confirmation_status, '') NOT IN ('rejected', 'expired')) "
            ."GROUP BY {$groupBy} HAVING COUNT(*) > 1"
            .') AS duplicate_groups'
        );

        return (int) ($row->duplicate_groups ?? 0);
    }
};
