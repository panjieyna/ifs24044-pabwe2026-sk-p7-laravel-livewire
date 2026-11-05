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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('min_stock')->default(5);
            $table->enum('status', ['available', 'low_stock', 'out_of_stock'])->default('out_of_stock');
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
