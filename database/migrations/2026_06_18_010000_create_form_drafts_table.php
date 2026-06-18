<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('form_drafts')) {
            return;
        }

        Schema::create('form_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('draft_key', 191);
            $table->string('title', 150)->nullable();
            $table->string('route_name', 191)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'draft_key'], 'form_drafts_scope_key_unique');
            $table->index(['company_id', 'user_id', 'updated_at'], 'form_drafts_scope_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_drafts');
    }
};
