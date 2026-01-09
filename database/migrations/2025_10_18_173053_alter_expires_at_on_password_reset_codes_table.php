<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove o DEFAULT CURRENT_TIMESTAMP e permite NULL
        DB::statement("
            ALTER TABLE password_reset_codes
            MODIFY expires_at TIMESTAMP NULL
        ");
    }

    public function down(): void
    {
        // Volta para NOT NULL com DEFAULT CURRENT_TIMESTAMP
        DB::statement("
            ALTER TABLE password_reset_codes
            MODIFY expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ");
    }
};
