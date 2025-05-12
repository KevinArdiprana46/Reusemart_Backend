<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penitip', function (Blueprint $table) {
            $table->integer('id_penitip')->primary();
            $table->unsignedBigInteger('id_alamat')->nullable();
            $table->string('badge');
            $table->integer('poin_sosial');
            $table->string('nama_lengkap');
            $table->string('no_telepon');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('gender');
            $table->date('tanggal_lahir');
            $table->float('komisi');
            $table->float('bonus');
            $table->string('image_user')->nullable();
            $table->unsignedBigInteger('id_role');
            $table->string('foto_ktp');
            $table->string('no_ktp');
            $table->timestamps();

            $table->foreign('id_role')->references('id_role')->on('role')->onDelete('cascade');
            $table->foreign('id_alamat')->references('id_alamat')->on('alamat')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penitip');
    }
};
