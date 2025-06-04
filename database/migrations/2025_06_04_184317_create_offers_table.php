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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_id')->unique()->notNullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');

            $table->string('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            $table->string('currency_id')->nullable();

            $table->boolean('available')->default(true);
            $table->integer('stock_quantity')->default(0);

            $table->string('vendor')->nullable();
            $table->string('vendor_code')->nullable();
            $table->string('barcode')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
