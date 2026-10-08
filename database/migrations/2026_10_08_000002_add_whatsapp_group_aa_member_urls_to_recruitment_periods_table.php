<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah link grup WA per tipe keanggotaan (AA/Member) per periode oprec.
     */
    public function up(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->string('whatsapp_group_aa_url', 255)->nullable()->after('whatsapp_group_url');
            $table->string('whatsapp_group_member_url', 255)->nullable()->after('whatsapp_group_aa_url');
        });
    }

    /**
     * Hapus link grup WA per tipe keanggotaan (rollback).
     */
    public function down(): void
    {
        Schema::table('recruitment_periods', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_group_member_url', 'whatsapp_group_aa_url']);
        });
    }
};
