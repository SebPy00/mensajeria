<?php

use App\Exports\SolicitudPagaresPendientesExport;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/maketxt/{nrofactura}', function ($nrofactura) {
//     return '<h1>factura: ' . $nrofactura . '</h1>';
// });

// Route::get('/excel', function ($codEntidad, $tipoDoc) {
//     //return Excel::download(new SolicitudPagaresPendientesExport($codEntidad), 'pagares_pendientes.xlsx');
//     return Excel::store(new SolicitudPagaresPendientesExport($codEntidad, $tipoDoc), 'pagares_pendientes.xlsx', 's4');
// });