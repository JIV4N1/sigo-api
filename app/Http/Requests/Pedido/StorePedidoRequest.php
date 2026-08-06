<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para crear un nuevo pedido de venta con sus detalles.
 * subtotal, iva y total se calculan en el controlador, no se reciben del cliente.
 * El pedido siempre inicia en estado "pendiente".
 */
class StorePedidoRequest extends FormRequest
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
            'fecha_pedido'                => 'sometimes|nullable|date',
            'fecha_entrega'               => 'nullable|date|after_or_equal:today',
            'observaciones'               => 'nullable|string',
            'detalles'                    => 'required|array|min:1',
            'detalles.*.material_id'      => 'required|integer|exists:materiales,id',
            'detalles.*.cantidad'         => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario'  => 'required|numeric|min:0',
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
            'fecha_pedido.date'                  => 'La fecha del pedido no tiene un formato válido.',
            'fecha_entrega.date'                 => 'La fecha de entrega no tiene un formato válido.',
            'fecha_entrega.after_or_equal'        => 'La fecha de entrega no puede ser anterior a hoy.',
            'detalles.required'                  => 'Debe incluir al menos un detalle.',
            'detalles.array'                     => 'Los detalles deben enviarse como una lista.',
            'detalles.min'                        => 'Debe incluir al menos un detalle.',
            'detalles.*.material_id.required'    => 'El material es obligatorio en cada detalle.',
            'detalles.*.material_id.exists'      => 'Uno de los materiales seleccionados no existe.',
            'detalles.*.cantidad.required'       => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min'            => 'La cantidad debe ser mayor a cero.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio en cada detalle.',
            'detalles.*.precio_unitario.min'     => 'El precio unitario no puede ser negativo.',
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
