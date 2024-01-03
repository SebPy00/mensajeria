<?php

namespace App\Http\Controllers;
use App\Models\GestionesAlarmasPY;
use Illuminate\Http\Request;

class GestionesAlarmasPYController extends Controller
{
    public function index(Request $request)
    {
        $apiKey = $request->input('api_key');

        // Verificar la clave del usuario
        if ($apiKey !== env('API_ALARMASPY_KEY_ID')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }else{
            $fecha = $request->input('fecha'); // Obtener la fecha de la solicitud
            $gestiones = GestionesAlarmasPY::where('fecha_gestion', $fecha)->get();
            return response()->json($gestiones);
        }
    }
}
