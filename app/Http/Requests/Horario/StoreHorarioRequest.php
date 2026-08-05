<?php

namespace App\Http\Requests\Horario;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para crear o actualizar el horario laboral de un día
 * de la semana (upsert por empresa_id + dia_semana, ver HorarioController::store).
 */
class StoreHorarioRequest extends FormRequest
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
            'dia_semana'  => 'required|numeric|between:1,7',
            'hora_inicio' => 'required_if:es_laboral,true|nullable|date_format:H:i',
            'hora_fin'    => 'required_if:es_laboral,true|nullable|date_format:H:i|after:hora_inicio',
            'es_laboral'  => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dia_semana.required'    => 'El día de la semana es obligatorio.',
            'dia_semana.numeric'     => 'El día de la semana debe ser un número entero (1-7).',
            'dia_semana.between'     => 'El día de la semana debe ser un número entre 1 (lunes) y 7 (domingo).',
            'hora_inicio.required_if' => 'La hora de inicio es obligatoria para un día laboral.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'hora_fin.required_if'    => 'La hora de fin es obligatoria para un día laboral.',
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
