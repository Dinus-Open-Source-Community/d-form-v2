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
        Schema::create('forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("title", 100);
            $table->text("description");
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('input_type', ['textInput', 'selectInput', 'textarea', 'datePicker', 'fileUpload'], 'textInput');
            $table->json('metadata');
            $table->uuid('form_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('form_id')
                ->references('id')
                ->on('forms')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');
    }
};
