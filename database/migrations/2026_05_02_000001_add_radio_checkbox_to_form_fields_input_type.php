<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // SQLite stores column types as plain text and does not enforce ENUMs,
        // so the MODIFY COLUMN statement is a no-op there.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE form_fields MODIFY COLUMN input_type
             ENUM('input','selectInput','textarea','datePicker','fileUpload','radio','checkbox')
             NOT NULL DEFAULT 'input'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE form_fields MODIFY COLUMN input_type
             ENUM('input','selectInput','textarea','datePicker','fileUpload')
             NOT NULL DEFAULT 'input'"
        );
    }
};
