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
        Schema::create('messages_hub', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type'); // classroom, student, guardian
            $table->unsignedBigInteger('target_id');
            $table->string('title');
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'target_type', 'target_id']);
        });

        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_type')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('channel')->default('in_app');
            $table->string('status')->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('messages_hub');
    }
};
