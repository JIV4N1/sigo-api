<?php

namespace App\Http\Requests\Horario;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para editar un horario existente.
 * Todos los campos son opcionales (sometimes) para permitir actualizaciones parciales.
 */
class UpdateHorarioRequest extends FormRequest
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
            'dia_semana'  => 'sometimes|required|numeric|between:1,7',
            'hora_inicio' => 'sometimes|nullable|date_format:H:i',
            'hora_fin'    => 'sometimes|nullable|date_format:H:i|after:hora_inicio',
            'es_laboral'  => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dia_semana.numeric'      => 'El día de la semana debe ser un número entero (1-7).',
            'dia_semana.between'      => 'El día de la semana debe ser un número entre 1 (lunes) y 7 (domingo).',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'hora_fin.date_format'    => 'La hora de fin debe tener el formato HH:MM.',
            'hora_fin.after'          => 'La hora de fin debe ser posterior a la hora de inicio.',
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
