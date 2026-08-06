<?php

namespace App\Http\Requests\Pedido;

use App\Models\Pedido;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida el cambio de estado de un pedido de venta.
 */
class CambiarEstadoPedidoRequest extends FormRequest
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
            'estado' => 'required|string|in:' . implode(',', Pedido::ESTADOS),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in'       => 'El estado debe ser uno de: ' . implode(', ', Pedido::ESTADOS) . '.',
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
