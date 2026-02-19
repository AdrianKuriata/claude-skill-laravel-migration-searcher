<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX idx_users_email ON users (email)');

        DB::unprepared('ALTER TABLE orders ADD CONSTRAINT chk_total CHECK (total >= 0)');

        DB::table('products')
            ->where('price', '>', 0)
            ->update([
                'tax' => DB::raw('price * 0.23'),
            ]);

        $sql = <<<SQL
            INSERT INTO audit_log (action, created_at)
            VALUES ('migration_run', NOW())
        SQL;

        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX idx_users_email ON users');
    }
};
