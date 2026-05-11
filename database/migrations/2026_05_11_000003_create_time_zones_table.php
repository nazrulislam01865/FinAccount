<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('time_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('utc_offset', 20);
            $table->string('php_timezone');
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_zones');
    }
};
