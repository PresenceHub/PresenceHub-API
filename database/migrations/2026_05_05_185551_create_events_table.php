<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->uuid('user_uuid')->nullable();
            $table->uuid('workspace_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('user_uuid');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
