<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesVistaCPH extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'cph.baseclientes';
    public $timestamps = false;
}
