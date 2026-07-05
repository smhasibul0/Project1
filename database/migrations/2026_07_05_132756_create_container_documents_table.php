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
        Schema::create('container_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->string('type');          // B/L, Invoice, Packing List, ...
            $table->string('provided_by')->nullable(); // RTC, Bank, ...
            $table->string('file');          // uploaded document filename
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_documents');
    }
};
