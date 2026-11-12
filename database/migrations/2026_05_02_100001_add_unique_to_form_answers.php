<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_answers', function (Blueprint $table) {
            $table->unique(['user_id', 'form_id'], 'form_answers_user_form_unique');
        });
    }

    public function down(): void
    {
        // InnoDB binds `form_answers_user_id_foreign` to the unique index below
        // (error 1553), so the key is dropped first and restored afterwards,
        // which recreates its original auto index and leaves no extra index.
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        if ($isMysql && $this->mysqlForeignKeyExists('form_answers', 'form_answers_user_id_foreign')) {
            Schema::table('form_answers', function (Blueprint $table) {
                $table->dropForeign('form_answers_user_id_foreign');
            });
        }

        if (Schema::hasIndex('form_answers', 'form_answers_user_form_unique')) {
            Schema::table('form_answers', function (Blueprint $table) {
                $table->dropUnique('form_answers_user_form_unique');
            });
        }

        if ($isMysql && ! $this->mysqlForeignKeyExists('form_answers', 'form_answers_user_id_foreign')) {
            Schema::table('form_answers', function (Blueprint $table) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate();
            });
        }
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
