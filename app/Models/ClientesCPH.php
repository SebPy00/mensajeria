<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesCPH extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'cph.clientes';
    public $timestamps = false;
}
