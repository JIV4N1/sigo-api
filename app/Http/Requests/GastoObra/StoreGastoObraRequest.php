<?php

namespace App\Http\Requests\GastoObra;

use App\Http\Traits\AdminBypassTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida la creación de un gasto de obra, con soporte opcional para
 * comprobante (archivo). empresa_id y usuario_id nunca se aceptan del
 * cliente: se asignan en el controlador a partir del usuario autenticado.
 *
 * proyecto_id además valida acceso: administrador/gerente pueden registrar
 * gastos en cualquier proyecto de su empresa; supervisor/ingeniero solo en
 * proyectos donde estén asignados (misma regla que el resto de la API,
 * vía AdminBypassTrait::tieneAccesoAProyecto()).
 */
class StoreGastoObraRequest extends FormRequest
{
    use AdminBypassTrait;

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
            'proyecto_id' => [
                'required',
                'integer',
                'exists:proyectos,id',
                function ($attribute, $value, $fail) {
                    if (! $this->tieneAccesoAProyecto($this, (int) $value)) {
                        $fail('No tienes acceso a este proyecto.');
                    }
                },
            ],
            'categoria_id' => 'required|integer|exists:categorias_gasto,id',
            'proveedor_id' => 'nullable|integer|exists:proveedores,id',
            'monto'        => 'required|numeric|min:0.01',
            'fecha'        => 'required|date',
            'descripcion'  => 'nullable|string',
            'comprobante'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proyecto_id.required'  => 'El proyecto es obligatorio.',
            'proyecto_id.exists'    => 'El proyecto seleccionado no existe.',
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists'   => 'La categoría seleccionada no existe.',
            'proveedor_id.exists'   => 'El proveedor seleccionado no existe.',
            'monto.required'        => 'El monto es obligatorio.',
            'monto.numeric'         => 'El monto debe ser un número.',
            'monto.min'             => 'El monto debe ser mayor a cero.',
            'fecha.required'        => 'La fecha es obligatoria.',
            'fecha.date'            => 'La fecha no tiene un formato válido.',
            'comprobante.file'      => 'El comprobante debe ser un archivo válido.',
            'comprobante.mimes'     => 'El comprobante debe ser un archivo PDF, JPG, JPEG o PNG.',
            'comprobante.max'       => 'El comprobante no puede superar los 2 MB.',
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
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
