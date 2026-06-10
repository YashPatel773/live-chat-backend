<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            
            // Link columns to the id on your users table
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            
            $table->text('message'); 
            $table->boolean('is_seen')->default(false); // 0 = unread, 1 = read
            $table->timestamps(); // Generates created_at and updated_at automatically
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};