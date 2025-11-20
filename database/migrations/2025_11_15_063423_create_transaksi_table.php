<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransaksiTable extends Migration
{
    public function up()
{
    Schema::create('transaksi', function (Blueprint $table) {
        $table->id();
        $table->string('kode_transaksi', 20);
        $table->dateTime('tanggal');
        $table->integer('total');
        $table->integer('bayar')->nullable();
        $table->integer('kembalian')->nullable();
        $table->enum('metode', ['tunai', 'online']);
        $table->unsignedBigInteger('pelanggan_id')->nullable();
        $table->integer('diskon_nominal')->default(0);
        $table->integer('grand_total');
        $table->integer('profit')->nullable();
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
    });
}


    public function down()
    {
        Schema::dropIfExists('transaksi');
    }
}
