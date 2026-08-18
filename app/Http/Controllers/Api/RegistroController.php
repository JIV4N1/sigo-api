<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registro\RegistroRequest;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Auto-registro público de usuarios en la API SIGO.
 *
 * La cuenta queda pendiente de aprobación (sin rol asignado) hasta que un
 * superadmin la revise — ver Superadmin\SolicitudesController.
 */
class RegistroController extends Controller
{
    /**
     * POST /api/registro
     */
    public function registrar(RegistroRequest $request): JsonResponse
    {
        $usuario = Usuario::create([
            'nombre'           => $request->nombre,
            'email'            => $request->email,
            'password'         => Hash::make($request->password),
            'telefono'         => $request->telefono,
            'empresa_id'       => $request->empresa_id,
            'departamento_id'  => $request->departamento_id,
            'rol_id'           => null,
            'activo'           => true,
            'aprobado'         => false,
            'rechazado'        => false,
            'fecha_solicitud'  => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Registro exitoso. Espera la aprobación del administrador.',
            'data'    => [
                'id'     => $usuario->id,
                'nombre' => $usuario->nombre,
                'email'  => $usuario->email,
            ],
        ], 201);
    }
}
