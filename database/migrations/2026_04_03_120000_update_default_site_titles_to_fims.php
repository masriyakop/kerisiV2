<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align seeded default branding with Financial Information Management System
     * when settings still use the previous template default.
     */
    public function up(): void
    {
        foreach (['siteTitle', 'webfrontTitle'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', 'CORRAD Laravel')
                ->update(['value' => 'Financial Information Management System', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (['siteTitle', 'webfrontTitle'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->where('value', 'Financial Information Management System')
                ->update(['value' => 'CORRAD Laravel', 'updated_at' => now()]);
        }
    }
};
