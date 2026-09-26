<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_barangs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('barang_id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('total_stok');
            $table->timestamps();

            $table->foreign('barang_id')->references('id')->on('barangs')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('user')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_barangs');
    }
};
