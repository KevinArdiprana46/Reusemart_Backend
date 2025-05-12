<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('role')) {
            Schema::create('role', function (Blueprint $table) {
                $table->id('id_role');
                $table->string('nama_role');
            });
        }
    }
};