<?php

namespace App\Http\Requests\ReporteHoras;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los filtros de consulta del reporte de horas trabajadas.
 */
class ReporteHorasRequest extends FormRequest
{
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
            'desde'       => 'required|date',
            'hasta'       => 'required|date|after_or_equal:desde',
            'usuario_id'  => 'nullable|integer|exists:usuarios,id',
            'proyecto_id' => 'nullable|integer|exists:proyectos,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'desde.required'       => 'La fecha de inicio (desde) es obligatoria.',
            'desde.date'           => 'La fecha de inicio no tiene un formato válido.',
            'hasta.required'       => 'La fecha de fin (hasta) es obligatoria.',
            'hasta.date'           => 'La fecha de fin no tiene un formato válido.',
            'hasta.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'usuario_id.exists'    => 'El usuario indicado no existe.',
            'proyecto_id.exists'   => 'El proyecto indicado no existe.',
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
