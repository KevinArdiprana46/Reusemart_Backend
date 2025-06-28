<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SafeToArray;

class FotoMerchandise extends Model
{
    use SafeToArray;
    
    protected $table = 'foto_merchandise';
    public $primaryKey = 'id_fotoM';

    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'id_merchandise',
        'foto_merchandise',
    ];

    public function merchandise()
    {
        return $this->belongsTo(Merchandise::class, 'id_merchandise');
    }
}
