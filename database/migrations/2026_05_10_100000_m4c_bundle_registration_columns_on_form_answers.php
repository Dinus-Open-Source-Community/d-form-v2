<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('form_answers', 'group_token')) {
                $table->string('group_token', 8)->nullable()->after('invitation_token');
            }

            if (! Schema::hasColumn('form_answers', 'member_confirmation_status')) {
                $table->string('member_confirmation_status', 16)->nullable()->after('group_token');
            }

            if (! Schema::hasColumn('form_answers', 'invited_email')) {
                $table->string('invited_email')->nullable()->after('member_confirmation_status');
            }

            if (! Schema::hasColumn('form_answers', 'member_confirmed_at')) {
                $table->timestamp('member_confirmed_at')->nullable()->after('invited_email');
            }

            if (! Schema::hasColumn('form_answers', 'invitation_expired_at')) {
                $table->timestamp('invitation_expired_at')->nullable()->after('member_confirmed_at');
            }
        });

        if (Schema::hasColumn('form_answers', 'status_confirmation_member')) {
            DB::table('form_answers')
                ->where('registration_role', 'leader')
                ->update(['member_confirmation_status' => 'accepted']);

            DB::table('form_answers')
                ->where('registration_role', 'member')
                ->where('status_confirmation_member', false)
                ->update(['member_confirmation_status' => 'pending']);

            DB::table('form_answers')
                ->where('registration_role', 'member')
                ->where('status_confirmation_member', true)
                ->update(['member_confirmation_status' => 'accepted']);

            Schema::table('form_answers', function (Blueprint $table) {
                $table->dropColumn('status_confirmation_member');
            });
        }

        Schema::table('form_answers', function (Blueprint $table) {
            $table->index(['form_id', 'group_token'], 'form_answers_form_id_group_token_index');
        });
    }

    public function down(): void
    {
        // InnoDB binds `form_answers_form_id_foreign` to this index (error 1553),
        // so the key is dropped first and restored afterwards, which recreates
        // its original auto index and leaves no extra index behind.
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        if ($isMysql && $this->mysqlForeignKeyExists('form_answers', 'form_answers_form_id_foreign')) {
            Schema::table('form_answers', function (Blueprint $table) {
                $table->dropForeign('form_answers_form_id_foreign');
            });
        }

        if (Schema::hasIndex('form_answers', 'form_answers_form_id_group_token_index')) {
            Schema::table('form_answers', function (Blueprint $table) {
                $table->dropIndex('form_answers_form_id_group_token_index');
            });
        }

        if ($isMysql && ! $this->mysqlForeignKeyExists('form_answers', 'form_answers_form_id_foreign')) {
            Schema::table('form_answers', function (Blueprint $table) {
                $table->foreign('form_id')
                    ->references('id')
                    ->on('forms')
                    ->cascadeOnUpdate();
            });
        }

        Schema::table('form_answers', function (Blueprint $table) {
            if (Schema::hasColumn('form_answers', 'invitation_expired_at')) {
                $table->dropColumn('invitation_expired_at');
            }

            if (Schema::hasColumn('form_answers', 'member_confirmed_at')) {
                $table->dropColumn('member_confirmed_at');
            }

            if (Schema::hasColumn('form_answers', 'invited_email')) {
                $table->dropColumn('invited_email');
            }

            if (Schema::hasColumn('form_answers', 'member_confirmation_status')) {
                $table->dropColumn('member_confirmation_status');
            }

            if (Schema::hasColumn('form_answers', 'group_token')) {
                $table->dropColumn('group_token');
            }
        });

        Schema::table('form_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('form_answers', 'status_confirmation_member')) {
                $table->boolean('status_confirmation_member')->default(true)->after('registration_role');
            }
        });
    }

    /** Check a foreign key through information_schema (MySQL path only). */
    private function mysqlForeignKeyExists(string $table, string $foreignKey): bool
    {
        return DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = ?",
            [Schema::getConnection()->getDatabaseName(), $table, $foreignKey]
        ) !== null;
    }
};
