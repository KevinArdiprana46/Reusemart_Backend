<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FotoBarang extends Model
{

  protected $table = 'foto_barang';
  public $primaryKey = 'id_foto';

  public $incrementing = true;
  public $timestamps = false;

  use HasFactory;

  protected $fillable = [
    'id_barang',
    'foto_barang',
  ];

  public function barang()
  {
    return $this->belongsTo(Barang::class, 'id_barang');
  }
}
