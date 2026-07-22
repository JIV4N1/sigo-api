<?php

namespace App\Http\Requests;

use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para crear un nuevo proyecto (obra).
 *
 * El campo 'codigo' se autogenera si no se envía.
 * Las asignaciones de personal son opcionales y se procesan en el controlador.
 */
class ProyectoStoreRequest extends FormRequest
{
    /**
     * Cualquier usuario autenticado puede intentar crear un proyecto.
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
            // --- Datos generales ---
            'nombre'       => 'required|string|max:200',
            'descripcion'  => 'nullable|string',
            'ubicacion'    => 'required|string|max:300',
            'latitud'      => 'nullable|numeric|between:-90,90',
            'longitud'     => 'nullable|numeric|between:-180,180',

            // --- Fechas ---
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',

            // --- Financiero / Estado ---
            'presupuesto'  => 'nullable|numeric|min:0',
            'avance'       => 'nullable|numeric|between:0,100',
            'estado'       => 'nullable|string|in:' . implode(',', Proyecto::ESTADOS),

            // --- Código (autogenerado si no se envía) ---
            'codigo'       => 'nullable|string|max:20|unique:proyectos,codigo',

            // --- Relaciones ---
            'cliente_id'   => 'nullable|integer|exists:clientes,id',

            // --- Imagen ---
            'imagen_portada' => 'nullable',

            // --- Asignaciones de personal (opcional al crear) ---
            'asignaciones'                  => 'nullable|array',
            'asignaciones.*.usuario_id'     => 'required_with:asignaciones|integer|exists:usuarios,id',
            'asignaciones.*.rol'            => 'nullable|string|in:' . implode(',', Proyecto::ROLES_PROYECTO),
            'asignaciones.*.rol_en_proyecto' => 'nullable|string|in:' . implode(',', Proyecto::ROLES_PROYECTO),
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
            'nombre.required'          => 'El nombre del proyecto es obligatorio.',
            'nombre.max'               => 'El nombre no puede superar los 200 caracteres.',
            'fecha_inicio.required'    => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'        => 'La fecha de inicio no tiene un formato válido.',
            'fecha_fin.required'       => 'La fecha de finalización es obligatoria.',
            'fecha_fin.date'           => 'La fecha de finalización no tiene un formato válido.',
            'fecha_fin.after'          => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'presupuesto.numeric'      => 'El presupuesto debe ser un valor numérico.',
            'presupuesto.min'          => 'El presupuesto no puede ser negativo.',
            'avance.between'           => 'El avance debe estar entre 0 y 100.',
            'estado.in'                => 'El estado debe ser uno de: ' . implode(', ', Proyecto::ESTADOS) . '.',
            'codigo.unique'            => 'Ya existe un proyecto con ese código.',
            'cliente_id.exists'        => 'El cliente seleccionado no existe.',
            'latitud.between'          => 'La latitud debe estar entre -90 y 90.',
            'longitud.between'         => 'La longitud debe estar entre -180 y 180.',
            'asignaciones.*.usuario_id.required_with' => 'El ID de usuario es requerido en cada asignación.',
            'asignaciones.*.usuario_id.exists'        => 'Uno de los usuarios a asignar no existe en el sistema.',
            'asignaciones.*.rol.required_with'        => 'El rol es requerido en cada asignación.',
            'asignaciones.*.rol.in'                   => 'El rol debe ser uno de: ' . implode(', ', Proyecto::ROLES_PROYECTO) . '.',
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
