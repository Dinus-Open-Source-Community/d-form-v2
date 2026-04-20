<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('answers');
            $table->uuid('user_id');
            $table->uuid('form_id');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate();
            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_answers');
    }
};
