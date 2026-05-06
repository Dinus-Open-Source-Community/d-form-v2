<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('form_answers', 'registration_code')) {
                $table->string('registration_code', 32)->nullable()->unique()->after('reviewed_by');
            }
        });

        Schema::table('email_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('email_logs', 'notification_type')) {
                $table->string('notification_type', 48)->nullable()->after('status');
                $table->index('notification_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            if (Schema::hasColumn('email_logs', 'notification_type')) {
                $table->dropIndex(['notification_type']);
                $table->dropColumn('notification_type');
            }
        });

        Schema::table('form_answers', function (Blueprint $table) {
            if (Schema::hasColumn('form_answers', 'registration_code')) {
                $table->dropUnique(['registration_code']);
                $table->dropColumn('registration_code');
            }
        });
    }
};
