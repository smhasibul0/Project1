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
        Schema::create('warehouse_staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_staff_id')->constrained('warehouse_staff')->cascadeOnDelete();
            $table->string('title');
            $table->string('file');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_staff_documents');
    }
};
