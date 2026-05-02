<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('form_answers', function (Blueprint $table) {
            $table->dropUnique('form_answers_user_form_unique');
        });
    }
};
