<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailTransaksiTable extends Migration
{
  public function up()
{
    Schema::create('detail_transaksi', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('transaksi_id');
        $table->unsignedBigInteger('barang_id');
        $table->integer('qty');
        $table->integer('harga');
        $table->integer('diskon')->default(0);
        $table->integer('diskon_nominal')->default(0);
        $table->integer('subtotal');
        $table->timestamps();
    });
}


    public function down()
    {
        Schema::dropIfExists('detail_transaksi');
    }
}
