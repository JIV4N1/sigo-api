<?php

namespace App\Http\Requests;

use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para actualizar un proyecto existente.
 *
 * Todos los campos son opcionales (sometimes), solo se validan
 * si están presentes en el request. Esto permite actualizaciones parciales (PATCH-style)
 * usando el método PUT sin necesidad de enviar todos los campos.
 */
class ProyectoUpdateRequest extends FormRequest
{
    /**
     * Cualquier usuario autenticado puede intentar actualizar.
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
        // El ID del proyecto viene en la URL, no en el body
        $proyectoId = $this->route('id');

        return [
            // --- Datos generales ---
            'nombre'       => 'sometimes|required|string|max:200',
            'descripcion'  => 'sometimes|nullable|string',
            'ubicacion'    => 'sometimes|nullable|string|max:300',
            'latitud'      => 'sometimes|nullable|numeric|between:-90,90',
            'longitud'     => 'sometimes|nullable|numeric|between:-180,180',

            // --- Fechas ---
            'fecha_inicio' => 'sometimes|required|date',
            'fecha_fin'    => 'sometimes|required|date|after:fecha_inicio',

            // --- Financiero / Estado ---
            'presupuesto'  => 'sometimes|nullable|numeric|min:0',
            'avance'       => 'sometimes|nullable|numeric|between:0,100',
            'estado'       => 'sometimes|required|string|in:' . implode(',', Proyecto::ESTADOS),

            // --- Código (ignorar unicidad del mismo proyecto) ---
            'codigo' => "sometimes|required|string|max:20|unique:proyectos,codigo,{$proyectoId}",

            // --- Relaciones ---
            'cliente_id'   => 'sometimes|nullable|integer|exists:clientes,id',

            // --- Imagen ---
            'imagen_portada' => 'sometimes|nullable|string|max:255',

            // --- Asignaciones (reemplazar todas las asignaciones actuales) ---
            'asignaciones'              => 'sometimes|nullable|array',
            'asignaciones.*.usuario_id' => 'required_with:asignaciones|integer|exists:usuarios,id',
            'asignaciones.*.rol'        => 'required_with:asignaciones|string|in:' . implode(',', Proyecto::ROLES_PROYECTO),
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
            'fecha_inicio.date'        => 'La fecha de inicio no tiene un formato válido.',
            'fecha_fin.date'           => 'La fecha de finalización no tiene un formato válido.',
            'fecha_fin.after'          => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'presupuesto.numeric'      => 'El presupuesto debe ser un valor numérico.',
            'presupuesto.min'          => 'El presupuesto no puede ser negativo.',
            'avance.between'           => 'El avance debe estar entre 0 y 100.',
            'estado.in'                => 'El estado debe ser uno de: ' . implode(', ', Proyecto::ESTADOS) . '.',
            'codigo.unique'            => 'Ya existe otro proyecto con ese código.',
            'cliente_id.exists'        => 'El cliente seleccionado no existe.',
            'asignaciones.*.usuario_id.exists' => 'Uno de los usuarios a asignar no existe.',
            'asignaciones.*.rol.in'           => 'El rol debe ser uno de: ' . implode(', ', Proyecto::ROLES_PROYECTO) . '.',
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
