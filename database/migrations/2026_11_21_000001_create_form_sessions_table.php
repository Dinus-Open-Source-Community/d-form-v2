<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('form_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->date('session_date');
            $table->string('label')->nullable();
            $table->timestamps();
            $table->unique(['form_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_sessions');
    }
};
