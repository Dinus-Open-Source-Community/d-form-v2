<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('form_review_email_outboxes', function (Blueprint $table): void {
            $table->enum('action', ['accepted', 'rejected', 'du_issued'])->change();
        });
    }

    public function down(): void
    {
        // MySQL strict menolak ALTER enum selama baris `du_issued` masih ada.
        DB::table('form_review_email_outboxes')->where('action', 'du_issued')->delete();

        Schema::table('form_review_email_outboxes', function (Blueprint $table): void {
            $table->enum('action', ['accepted', 'rejected'])->change();
        });
    }
};
