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
        Schema::create('translation_translation_tag', function (Blueprint $table) {
            $table->foreignId('translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('translation_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['translation_id', 'translation_tag_id']);
            $table->index(['translation_tag_id', 'translation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_translation_tag');
    }
};
