<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    
    use HasFactory;
    protected $primaryKey = 'cli';
    protected $table = 'cli';

    public function operacion() {
        return $this->hasMany(Operacion::class, 'ope');
    }
}
