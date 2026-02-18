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
        // Schema::create('event_categories', function (Blueprint $table) {
        //     $table->uuid('id')->primary();

        //     $table->string('name', 100);
        //     $table->text('description');

        //     $table->timestamps();
        // });

        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 100);
            $table->text('description');
            $table->date('start_date');
            $table->date('end_date');
            $table->dateTime('registration_start');
            $table->dateTime('registration_end');
            $table->string('location');
            $table->unsignedSmallInteger('quota');
            $table->string('banner');
            $table->decimal('price', 10, 2);
            $table->string('session', 50);
            $table->string('status', 20);
            $table->string('category', 20);
            // $table->uuid('event_category_id');

            // $table->foreign('event_category_id')
            //     ->references('id')
            //     ->on('event_categories')
            //     ->cascadeOnUpdate()
            //     ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
        // Schema::dropIfExists('event_categories');2
    }
};
