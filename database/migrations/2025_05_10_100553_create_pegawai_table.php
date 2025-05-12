<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->integer('id_pegawai')->primary();
            $table->unsignedBigInteger('id_jabatan')->nullable();
            $table->unsignedBigInteger('id_role')->nullable();
            $table->string('nama_lengkap');
            $table->string('alamat');
            $table->string('no_telepon');
            $table->string('email')->unique();
            $table->string('gender');
            $table->date('tanggal_lahir');
            $table->string('password');
            $table->double('komisi_hunter')->default(0);
            $table->string('image_user')->nullable();

            $table->timestamps();

            // Relasi foreign key
            $table->foreign('id_role')->references('id_role')->on('role')->onDelete('set null');
            $table->foreign('id_jabatan')->references('id_jabatan')->on('jabatan')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
