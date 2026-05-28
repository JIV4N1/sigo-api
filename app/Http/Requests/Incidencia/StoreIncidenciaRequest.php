<?php

namespace App\Http\Requests\Incidencia;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Form Request para validar la creación de una nueva incidencia.
 *
 * Aplica las reglas de negocio del sistema SIGO: categoría y severidad
 * restringidas a los valores del dominio, coordenadas GPS opcionales con
 * rangos geográficos válidos, y fotos opcionales con límite de tamaño.
 */
class StoreIncidenciaRequest extends FormRequest
{
    /**
     * Cualquier usuario autenticado puede reportar incidencias.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la nueva incidencia.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Proyecto al que pertenece la incidencia (debe existir en la tabla)
            'proyecto_id'            => ['required', 'integer', 'exists:proyectos,id'],

            // Título descriptivo de la incidencia
            'titulo'                 => ['required', 'string', 'min:10', 'max:200'],

            // Descripción detallada del problema reportado
            'descripcion'            => ['required', 'string', 'min:20', 'max:1000'],

            // Categoría del tipo de incidencia
            'categoria'              => ['required', Rule::in([
                'seguridad', 'falta_material', 'falla_equipo', 'clima', 'otro',
            ])],

            // Nivel de severidad o criticidad de la incidencia
            'severidad'              => ['required', Rule::in([
                'baja', 'media', 'alta', 'critica',
            ])],

            // Coordenadas GPS opcionales (para localizar la incidencia en obra)
            'latitud'                => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'               => ['nullable', 'numeric', 'between:-180,180'],

            // Descripción textual de la ubicación dentro de la obra
            'ubicacion_descripcion'  => ['nullable', 'string', 'max:300'],

            // Fotos de evidencia: opcionales, máximo 6 imágenes de hasta 5 MB cada una
            'fotos'                  => ['nullable', 'array', 'max:6'],
            'fotos.*'                => ['image', 'max:5120'], // 5120 KB = 5 MB
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
            'proyecto_id.required'           => 'El proyecto es obligatorio.',
            'proyecto_id.exists'             => 'El proyecto seleccionado no existe.',
            'titulo.required'                => 'El título es obligatorio.',
            'titulo.min'                     => 'El título debe tener al menos 10 caracteres.',
            'titulo.max'                     => 'El título no puede superar los 200 caracteres.',
            'descripcion.required'           => 'La descripción es obligatoria.',
            'descripcion.min'                => 'La descripción debe tener al menos 20 caracteres.',
            'descripcion.max'                => 'La descripción no puede superar los 1000 caracteres.',
            'categoria.required'             => 'La categoría es obligatoria.',
            'categoria.in'                   => 'La categoría debe ser: seguridad, falta_material, falla_equipo, clima u otro.',
            'severidad.required'             => 'La severidad es obligatoria.',
            'severidad.in'                   => 'La severidad debe ser: baja, media, alta o critica.',
            'latitud.numeric'                => 'La latitud debe ser un número válido.',
            'latitud.between'                => 'La latitud debe estar entre -90 y 90.',
            'longitud.numeric'               => 'La longitud debe ser un número válido.',
            'longitud.between'               => 'La longitud debe estar entre -180 y 180.',
            'ubicacion_descripcion.max'      => 'La descripción de ubicación no puede superar los 300 caracteres.',
            'fotos.array'                    => 'Las fotos deben enviarse como arreglo.',
            'fotos.max'                      => 'Se permite un máximo de 6 fotos por incidencia.',
            'fotos.*.image'                  => 'Cada archivo debe ser una imagen válida (jpg, png, webp).',
            'fotos.*.max'                    => 'Cada foto no puede pesar más de 5 MB.',
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
