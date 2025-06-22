<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('sorteio_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorteio_id')->constrained()->onDelete('cascade');
            $table->string('nome')->nullable();
            $table->decimal('media', 4, 2)->default(0);
            $table->timestamps();
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorteio_times');
    }
};
