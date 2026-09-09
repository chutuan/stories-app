<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->longText('content');
            $table->timestamps();

            $table->unique(['story_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
