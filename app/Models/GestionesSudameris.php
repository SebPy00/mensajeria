<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionesSudameris extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'sudameris.gestiones';
    public $timestamps = false;
}
