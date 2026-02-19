<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('is_active', false)
            ->update([
                'status' => 'inactive',
                'deactivated_at' => now(),
            ]);

        DB::table('settings')->insert([
            'key' => 'app_version',
            'value' => '2.0',
        ]);

        DB::table('old_logs')
            ->where('created_at', '<', '2023-01-01')
            ->delete();
    }

    public function down(): void
    {
        // Data migration - not reversible
    }
};
