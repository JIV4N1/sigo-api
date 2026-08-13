<?php

namespace App\Http\Requests;

use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para asignar varios usuarios a un proyecto en una sola petición.
 */
class AsignacionMasivaRequest extends FormRequest
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
            'usuario_ids'     => 'required|array|min:1',
            'usuario_ids.*'   => 'integer|exists:usuarios,id',
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
            'usuario_ids.required' => 'Debes indicar al menos un usuario.',
            'usuario_ids.array'    => 'usuario_ids debe ser un arreglo.',
            'usuario_ids.min'      => 'Debes indicar al menos un usuario.',
            'usuario_ids.*.integer' => 'Cada usuario_id debe ser un número entero.',
            'usuario_ids.*.exists' => 'Uno o más usuarios especificados no existen en el sistema.',
            'rol.in'                => 'El rol debe ser uno de: ' . implode(', ', Proyecto::ROLES_PROYECTO) . '.',
            'rol_en_proyecto.in'    => 'El rol debe ser uno de: ' . implode(', ', Proyecto::ROLES_PROYECTO) . '.',
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
