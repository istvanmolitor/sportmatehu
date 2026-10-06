<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_target_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id');
            $table->string('name');
            $table->string('full_name');
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('language')->nullable();
            $table->unsignedInteger('stargazers_count')->default(0);
            $table->unsignedInteger('open_issues_count')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('github_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['sync_target_id', 'github_id']);
            $table->index('stargazers_count');
            $table->index('open_issues_count');
            $table->index('github_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
