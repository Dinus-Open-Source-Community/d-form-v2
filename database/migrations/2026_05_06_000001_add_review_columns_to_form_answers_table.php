<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('form_answers', 'review_status')) {
                $table->string('review_status', 20)->default('pending')->after('form_id');
            }
            if (! Schema::hasColumn('form_answers', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_status');
            }
            if (! Schema::hasColumn('form_answers', 'reviewed_by')) {
                $table->uuid('reviewed_by')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('form_answers', function (Blueprint $table) {
            // Keep FK separate so `Schema::hasColumn` checks above run first.
            if (Schema::hasColumn('form_answers', 'reviewed_by')) {
                $table->index(['review_status'], 'form_answers_review_status_index');
                $table->index(['reviewed_by'], 'form_answers_reviewed_by_index');

                $table->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            if (Schema::hasColumn('form_answers', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
                $table->dropIndex('form_answers_reviewed_by_index');
            }

            if (Schema::hasColumn('form_answers', 'review_status')) {
                $table->dropIndex('form_answers_review_status_index');
            }

            if (Schema::hasColumn('form_answers', 'review_status')) {
                $table->dropColumn('review_status');
            }
            if (Schema::hasColumn('form_answers', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('form_answers', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }
        });
    }
};

