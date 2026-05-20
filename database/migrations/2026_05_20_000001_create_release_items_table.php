<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_items', function (Blueprint $table) {
            $table->id();
            $table->date('release_date')->index();
            $table->string('module', 100)->index();
            $table->string('ui_function', 40);
            $table->string('item_type', 40)->index();
            $table->string('task', 180);
            $table->text('note')->nullable();
            $table->text('user_impact')->nullable();
            $table->string('released_by', 120)->nullable();
            $table->string('release_version', 40)->index();
            $table->string('status', 40)->default('Released')->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['release_date', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_items');
    }
};
