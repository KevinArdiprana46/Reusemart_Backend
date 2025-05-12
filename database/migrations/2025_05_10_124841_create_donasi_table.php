<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDonasiTable extends Migration
{
    public function up()
    {
        Schema::create('donasi', function (Blueprint $table) {
            $table->integer('id_donasi')->primary();
            $table->string('nama_barang', 255);
            $table->string('pesan_request', 255)->nullable();
            $table->string('status_donasi', 255)->nullable();
            $table->date('tanggal_donasi')->nullable();
            $table->unsignedBigInteger('id_barang');
            $table->unsignedBigInteger('id_organisasi');

            $table->foreign('id_barang')->references('id_barang')->on('barang')->onDelete('set null');
            $table->foreign('id_organisasi')->references('id_organisasi')->on('organisasi')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('donasi');
    }
}
