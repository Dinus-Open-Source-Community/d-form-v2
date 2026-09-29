<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_datasets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('source_type', 40);
            $table->string('source_id', 100)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by', 'email_datasets_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index('source_type', 'email_datasets_source_type_idx');
        });

        Schema::create('email_dataset_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('name', 150)->nullable();
            $table->string('email', 255);
            $table->timestamps();

            $table->foreign('dataset_id', 'email_ds_recipients_dataset_fk')
                ->references('id')->on('email_datasets')->cascadeOnDelete();
            $table->index(['dataset_id', 'email'], 'email_ds_recipients_ds_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_dataset_recipients');
        Schema::dropIfExists('email_datasets');
    }
};
