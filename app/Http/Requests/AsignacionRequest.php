<?php

namespace App\Http\Requests;

use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para asignar un usuario a un proyecto.
 *
 * Se usa tanto para asignar (POST) como para actualizar el rol
 * de una asignación existente.
 */
class AsignacionRequest extends FormRequest
{
    /**
     * Cualquier usuario autenticado puede intentar asignar.
     * La verificación de rol (gerente/administrador) se hace en el controlador.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usuario_id'      => 'required|integer|exists:usuarios,id',
            'rol'             => 'nullable|string|in:' . implode(',', Proyecto::ROLES_PROYECTO),
            'rol_en_proyecto' => 'nullable|string|in:' . implode(',', Proyecto::ROLES_PROYECTO),
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario_id.required' => 'El ID del usuario es obligatorio.',
            'usuario_id.integer'  => 'El ID del usuario debe ser un número entero.',
            'usuario_id.exists'   => 'El usuario especificado no existe en el sistema.',
            'rol.required'        => 'El rol es obligatorio.',
            'rol.in'              => 'El rol debe ser uno de: ' . implode(', ', Proyecto::ROLES_PROYECTO) . '.',
        ];
    }

    /**
     * Retorna errores de validación en el formato estándar de la API SIGO.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => 'Error de validación.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
