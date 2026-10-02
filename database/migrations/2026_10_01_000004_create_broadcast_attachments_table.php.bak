<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel lampiran broadcast (maks 3 file, pdf/jpg/png ≤5MB).
     */
    public function up(): void
    {
        Schema::create('broadcast_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('broadcast_id');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->cascadeOnDelete();
        });
    }

    /**
     * Hapus tabel lampiran broadcast (rollback Task 3c).
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_attachments');
    }
};
