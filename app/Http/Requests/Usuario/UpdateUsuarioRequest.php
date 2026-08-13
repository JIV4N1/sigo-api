<?php

namespace App\Http\Requests\Usuario;

use App\Http\Traits\AdminBypassTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Valida los datos para actualizar un usuario existente.
 *
 * Todos los campos son opcionales (sometimes) para permitir actualizaciones
 * parciales vía PUT. empresa_id no se acepta desde el body. La verificación
 * de rol (administrador) se hace en el controlador.
 */
class UpdateUsuarioRequest extends FormRequest
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
        // El ID del usuario viene en la URL, no en el body
        $usuarioId = $this->route('id');

        return [
            'nombre'   => 'sometimes|required|string|max:100',
            'email'    => "sometimes|required|email|max:100|unique:usuarios,email,{$usuarioId}",
            'password' => 'sometimes|required|string|min:8',
            'telefono' => 'sometimes|nullable|string|max:20',
            'rol_id'   => 'sometimes|required|integer|exists:roles,id',
            'activo'   => 'sometimes|boolean',
            'departamento_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departamentos', 'id')
                    ->where(fn ($query) => $query->where('empresa_id', $this->getEmpresaId($this))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.max'        => 'El nombre no puede superar los 100 caracteres.',
            'email.required'    => 'El correo electrónico es obligatorio.',
            'email.email'       => 'El correo electrónico no tiene un formato válido.',
            'email.unique'      => 'Ya existe otro usuario con ese correo electrónico.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'telefono.max'      => 'El teléfono no puede superar los 20 caracteres.',
            'rol_id.exists'     => 'El rol seleccionado no existe.',
            'activo.boolean'    => 'El campo activo debe ser verdadero o falso.',
            'departamento_id.exists' => 'El departamento seleccionado no existe o no pertenece a tu empresa.',
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
