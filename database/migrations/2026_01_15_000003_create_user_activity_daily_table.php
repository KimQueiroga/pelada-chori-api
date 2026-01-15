<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_activity_daily', function (Blueprint $table) {
            $table->id();
            $table->date('activity_date');
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 150);
            $table->unsignedInteger('requests_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_date', 'user_id']);
            $table->index('activity_date');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_daily');
    }
};
