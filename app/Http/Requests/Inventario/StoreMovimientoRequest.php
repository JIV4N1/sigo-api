<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida el registro de un movimiento de inventario (entrada/salida/ajuste).
 */
class StoreMovimientoRequest extends FormRequest
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
            'material_id'     => 'required|integer|exists:materiales,id',
            'tipo_movimiento' => 'required|string|in:entrada,salida,ajuste',
            'cantidad'        => 'required|numeric|min:0',
            'motivo'          => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_id.required'     => 'El material es obligatorio.',
            'material_id.exists'       => 'El material seleccionado no existe.',
            'tipo_movimiento.required' => 'El tipo de movimiento es obligatorio.',
            'tipo_movimiento.in'       => 'El tipo de movimiento debe ser uno de: entrada, salida, ajuste.',
            'cantidad.required'        => 'La cantidad es obligatoria.',
            'cantidad.numeric'         => 'La cantidad debe ser un número.',
            'cantidad.min'             => 'La cantidad no puede ser negativa.',
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
