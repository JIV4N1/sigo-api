<?php

namespace App\Http\Requests\Cotizacion;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para crear una nueva cotización con sus partidas (detalle de materiales).
 */
class StoreCotizacionRequest extends FormRequest
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
            'cliente_id'                  => 'required|integer|exists:clientes,id',
            'fecha'                       => 'required|date',
            'subtotal'                    => 'required|numeric|min:0',
            'iva'                         => 'required|numeric|min:0',
            'total'                       => 'required|numeric|min:0',
            'estado'                      => 'required|string|in:Pendiente,Aprobada,Rechazada,ConvertidaAVenta',
            'observaciones'               => 'nullable|string',
            'detalles'                    => 'required|array|min:1',
            'detalles.*.material_id'      => 'required|integer|exists:materiales,id',
            'detalles.*.cantidad'         => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario'  => 'required|numeric|min:0',
            'detalles.*.subtotal'         => 'required|numeric|min:0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.required'                => 'El cliente es obligatorio.',
            'cliente_id.exists'                  => 'El cliente seleccionado no existe.',
            'fecha.required'                      => 'La fecha es obligatoria.',
            'fecha.date'                           => 'La fecha no tiene un formato válido.',
            'subtotal.required'                   => 'El subtotal es obligatorio.',
            'subtotal.numeric'                    => 'El subtotal debe ser un número.',
            'subtotal.min'                        => 'El subtotal no puede ser negativo.',
            'iva.required'                        => 'El IVA es obligatorio.',
            'iva.numeric'                          => 'El IVA debe ser un número.',
            'iva.min'                              => 'El IVA no puede ser negativo.',
            'total.required'                      => 'El total es obligatorio.',
            'total.numeric'                       => 'El total debe ser un número.',
            'total.min'                           => 'El total no puede ser negativo.',
            'estado.required'                     => 'El estado es obligatorio.',
            'estado.in'                            => 'El estado debe ser uno de: Pendiente, Aprobada, Rechazada, ConvertidaAVenta.',
            'detalles.required'                   => 'Debe incluir al menos un detalle de material.',
            'detalles.array'                      => 'Los detalles deben enviarse como una lista.',
            'detalles.min'                        => 'Debe incluir al menos un detalle de material.',
            'detalles.*.material_id.required'     => 'El material es obligatorio en cada detalle.',
            'detalles.*.material_id.exists'       => 'Uno de los materiales seleccionados no existe.',
            'detalles.*.cantidad.required'        => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min'             => 'La cantidad debe ser mayor a cero.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio en cada detalle.',
            'detalles.*.precio_unitario.min'      => 'El precio unitario no puede ser negativo.',
            'detalles.*.subtotal.required'        => 'El subtotal es obligatorio en cada detalle.',
            'detalles.*.subtotal.min'             => 'El subtotal del detalle no puede ser negativo.',
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
