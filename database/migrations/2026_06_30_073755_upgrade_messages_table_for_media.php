<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) { 
            $table->text('message')->nullable()->change();
            $table->enum('type', ['text', 'image', 'video', 'document'])->default('text')->after('message');
            $table->string('file_path')->nullable()->after('type');
            $table->string('file_name')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('message')->nullable(false)->change();
            $table->dropColumn(['type', 'file_path', 'file_name']);
        });
    }
};