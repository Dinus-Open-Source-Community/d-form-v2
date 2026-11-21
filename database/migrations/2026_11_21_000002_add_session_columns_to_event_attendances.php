<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->foreignUuid('form_session_id')->nullable()->constrained('form_sessions')->nullOnDelete();
            $table->string('attendance_slot', 64)->default('default');
        });

        DB::table('event_attendances')->update(['attendance_slot' => 'default']);

        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->dropUnique(['event_id', 'form_answer_id']);
            $table->unique(['form_answer_id', 'attendance_slot']);
        });
    }

    public function down(): void
    {
        // Rollback fails if D-Kelas rows already reuse the same form_answer_id.
        Schema::table('event_attendances', function (Blueprint $table): void {
            $table->dropUnique(['form_answer_id', 'attendance_slot']);
            $table->unique(['event_id', 'form_answer_id']);
            $table->dropForeign(['form_session_id']);
            $table->dropColumn(['form_session_id', 'attendance_slot']);
        });

        Schema::dropIfExists('form_sessions');
    }
};
