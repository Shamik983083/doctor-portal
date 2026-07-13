<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change response_body to utf8mb4 so any multi-byte characters
        // in partner HTTP responses (em-dashes, special chars, emoji) can
        // be stored without a SQLSTATE[22007] charset error.
        DB::statement(
            'ALTER TABLE webhook_deliveries
             MODIFY response_body TEXT
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci
             NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE webhook_deliveries
             MODIFY response_body TEXT
             CHARACTER SET latin1
             COLLATE latin1_swedish_ci
             NULL'
        );
    }
};
