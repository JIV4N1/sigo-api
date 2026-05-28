<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Form Request para validar la creación de un reporte diario.
 *
 * Aplica reglas de negocio SIGO: fecha no futura, no más de 7 días atrás,
 * turno y categoría restringidos a valores permitidos, y fotos opcionales
 * con límite de peso por imagen.
 */
class StoreReporteRequest extends FormRequest
{
    /**
     * Cualquier usuario autenticado puede crear reportes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el nuevo reporte diario.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Proyecto al que pertenece el reporte (debe existir en la tabla)
            'proyecto_id'  => ['required', 'integer', 'exists:proyectos,id'],

            // Fecha del reporte: no futura y no más de 7 días atrás
            'fecha_reporte' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:' . now()->subDays(7)->toDateString(),
            ],

            // Turno de trabajo en que se realizó el reporte
            'turno'     => ['required', Rule::in(['matutino', 'vespertino', 'nocturno'])],

            // Categoría del trabajo reportado
            'categoria' => ['required', Rule::in([
                'estructural', 'albanileria', 'instalaciones', 'acabados', 'general',
            ])],

            // Porcentaje de avance registrado (0-100)
            'avance'      => ['required', 'numeric', 'min:0', 'max:100'],

            // Descripción detallada del trabajo realizado
            'descripcion' => ['required', 'string', 'min:20', 'max:500'],

            // Fotos opcionales: array de imágenes, máximo 5 MB cada una
            'fotos'   => ['nullable', 'array'],
            'fotos.*' => ['image', 'max:5120'], // 5120 KB = 5 MB
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
            'proyecto_id.required'       => 'El proyecto es obligatorio.',
            'proyecto_id.exists'         => 'El proyecto seleccionado no existe.',
            'fecha_reporte.required'     => 'La fecha del reporte es obligatoria.',
            'fecha_reporte.date'         => 'La fecha del reporte no tiene un formato válido.',
            'fecha_reporte.before_or_equal' => 'La fecha del reporte no puede ser futura.',
            'fecha_reporte.after_or_equal'  => 'La fecha del reporte no puede ser anterior a 7 días.',
            'turno.required'             => 'El turno es obligatorio.',
            'turno.in'                   => 'El turno debe ser: matutino, vespertino o nocturno.',
            'categoria.required'         => 'La categoría es obligatoria.',
            'categoria.in'               => 'La categoría debe ser: estructural, albanileria, instalaciones, acabados o general.',
            'avance.required'            => 'El avance es obligatorio.',
            'avance.numeric'             => 'El avance debe ser un número.',
            'avance.min'                 => 'El avance no puede ser negativo.',
            'avance.max'                 => 'El avance no puede superar el 100%.',
            'descripcion.required'       => 'La descripción es obligatoria.',
            'descripcion.min'            => 'La descripción debe tener al menos 20 caracteres.',
            'descripcion.max'            => 'La descripción no puede superar los 500 caracteres.',
            'fotos.array'                => 'Las fotos deben enviarse como arreglo.',
            'fotos.*.image'              => 'Cada archivo debe ser una imagen válida (jpg, png, webp).',
            'fotos.*.max'                => 'Cada foto no puede pesar más de 5 MB.',
        ];
    }

    /**
     * Respuesta JSON estándar SIGO cuando la validación falla.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => 'Los datos proporcionados no son válidos.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
