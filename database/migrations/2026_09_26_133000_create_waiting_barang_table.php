<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_barang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('barang_id');
            $table->string('kode_barang');
            $table->integer('stok')->default(0);
            $table->decimal('harga_beli', 12, 2);
            $table->decimal('harga_jual', 12, 2);

            $table->foreign('barang_id')->references('id')->on('barangs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_barang');
    }
};
