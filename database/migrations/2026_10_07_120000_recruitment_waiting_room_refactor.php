<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('recruitment_queue_entries');

        if (! Schema::hasColumn('recruitment_interviews', 'booked_at')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                Schema::table('recruitment_interviews', function (Blueprint $table): void {
                    $table->dropForeign(['interviewer_id']);
                });

                DB::statement('ALTER TABLE recruitment_interviews MODIFY interviewer_id CHAR(36) NULL');

                Schema::table('recruitment_interviews', function (Blueprint $table): void {
                    $table->dateTime('booked_at')->nullable()->after('interviewer_id');
                });

                Schema::table('recruitment_interviews', function (Blueprint $table): void {
                    $table->foreign('interviewer_id', 'rec_interviews_iv_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
        }

        if (! Schema::hasTable('recruitment_interviews')) {
            return;
        }

        DB::table('recruitment_interviews')
            ->whereIn('status', ['scheduled', 'checked_in', 'queued'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('recruitment_attendances')
                    ->whereColumn(
                        'recruitment_attendances.recruitment_application_id',
                        'recruitment_interviews.recruitment_application_id',
                    );
            })
            ->update([
                'status' => 'waiting',
                'interviewer_id' => null,
                'booked_at' => null,
            ]);

        DB::table('recruitment_interviews')
            ->whereIn('status', ['scheduled', 'checked_in', 'queued', 'no_show'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('recruitment_attendances')
                    ->whereColumn(
                        'recruitment_attendances.recruitment_application_id',
                        'recruitment_interviews.recruitment_application_id',
                    );
            })
            ->delete();

        DB::table('recruitment_interviews')
            ->where('status', 'called')
            ->whereNotNull('interviewer_id')
            ->update(['status' => 'in_progress']);

        DB::table('recruitment_interviews')
            ->where('status', 'called')
            ->whereNull('interviewer_id')
            ->update(['status' => 'waiting']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('recruitment_interviews', 'booked_at')) {
            Schema::table('recruitment_interviews', function (Blueprint $table): void {
                $table->dropColumn('booked_at');
            });
        }
    }
};
