<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\SafeToArray;

class Role extends Model
{
    use HasFactory, SafeToArray;

    protected $table = 'role';
    protected $primaryKey = 'id_role';
    public $timestamps = false;

    protected $fillable = [
        'nama_role',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'id_role');
    }
}

