<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('form_answers', 'leader_form_answer_id')) {
                $table->uuid('leader_form_answer_id')->nullable()->after('form_id');
                $table->foreign('leader_form_answer_id')
                    ->references('id')
                    ->on('form_answers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('form_answers', 'registration_role')) {
                $table->string('registration_role', 16)->nullable()->after('leader_form_answer_id');
            }

            if (! Schema::hasColumn('form_answers', 'status_confirmation_member')) {
                $table->boolean('status_confirmation_member')->default(true)->after('registration_role');
            }

            if (! Schema::hasColumn('form_answers', 'invitation_token')) {
                $table->string('invitation_token', 64)->nullable()->after('status_confirmation_member');
                $table->unique('invitation_token', 'form_answers_invitation_token_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            if (Schema::hasColumn('form_answers', 'invitation_token')) {
                $table->dropUnique('form_answers_invitation_token_unique');
                $table->dropColumn('invitation_token');
            }

            if (Schema::hasColumn('form_answers', 'status_confirmation_member')) {
                $table->dropColumn('status_confirmation_member');
            }

            if (Schema::hasColumn('form_answers', 'registration_role')) {
                $table->dropColumn('registration_role');
            }

            if (Schema::hasColumn('form_answers', 'leader_form_answer_id')) {
                $table->dropForeign(['leader_form_answer_id']);
                $table->dropColumn('leader_form_answer_id');
            }
        });
    }
};
