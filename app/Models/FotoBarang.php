<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotoBarang extends Model
{
    protected $table = 'foto_barang';
    public $primaryKey = 'id_foto';

    public $timestamps = false;

    use HasFactory;

    protected $fillable = [
      'foto_barang',  
    ];
}
