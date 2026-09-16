<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Data\User\UserData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $result = UserData::getUsers();

        return $result['success']
            ? $this->ok($result['data'], $result['message'], 200)
            : $this->error($result['message'], $result['message'] === 'Usuario no encontrado.' ? 404 : 400);
    }

    public function show(int $id)
    {
        $result = UserData::getUserById($id);

        return $result['success']
            ? $this->ok($result['data'], $result['message'], 200)
            : $this->error($result['message'], $result['message'] === 'Usuario no encontrado.' ? 404 : 400);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'correo' => ['required', 'email', 'max:150', Rule::unique('mysql.usuarios', 'correo')],
            'password' => 'required|string|min:8|max:255',
            'dni' => ['nullable', 'string', 'max:20', Rule::unique('mysql.usuarios', 'dni')],
            'telefono' => 'nullable|string|max:20',
            'sexo' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date|before_or_equal:today',
            'rol' => ['required', Rule::in(['user', 'admin'])],
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $result = UserData::createUser($validator->validated());

        return $result['success']
            ? $this->ok($result['data'], $result['message'], 201)
            : $this->error($result['message'], $result['message'] === 'Usuario no encontrado.' ? 404 : 400);
    }

    public function update(Request $request, int $id)
    {
        $existing = UserData::getUserById($id);

        if (! $existing['success']) {
            return $this->error($existing['message'], $existing['message'] === 'Usuario no encontrado.' ? 404 : 400);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'correo' => ['required', 'email', 'max:150', Rule::unique('mysql.usuarios', 'correo')->ignore($id, 'id_usuario')],
            'password' => 'nullable|string|min:8|max:255',
            'dni' => ['nullable', 'string', 'max:20', Rule::unique('mysql.usuarios', 'dni')->ignore($id, 'id_usuario')],
            'telefono' => 'nullable|string|max:20',
            'sexo' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date|before_or_equal:today',
            'rol' => ['required', Rule::in(['user', 'admin'])],
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $result = UserData::updateUser($id, $validator->validated());

        return $result['success']
            ? $this->ok($result['data'], $result['message'], 200)
            : $this->error($result['message'], $result['message'] === 'Usuario no encontrado.' ? 404 : 400);
    }

    public function patch(Request $request, int $id)
    {
        $existing = UserData::getUserById($id);

        if (! $existing['success']) {
            return $this->error($existing['message'], $existing['message'] === 'Usuario no encontrado.' ? 404 : 400);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'correo' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('mysql.usuarios', 'correo')->ignore($id, 'id_usuario')],
            'password' => 'sometimes|required|string|min:8|max:255',
            'dni' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('mysql.usuarios', 'dni')->ignore($id, 'id_usuario')],
            'telefono' => 'sometimes|nullable|string|max:20',
            'sexo' => 'sometimes|nullable|string|max:20',
            'fecha_nacimiento' => 'sometimes|nullable|date|before_or_equal:today',
            'rol' => ['sometimes', 'required', Rule::in(['user', 'admin'])],
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $result = UserData::updateUser($id, $validator->validated());

        return $result['success']
            ? $this->ok($result['data'], $result['message'], 200)
            : $this->error($result['message'], $result['message'] === 'Usuario no encontrado.' ? 404 : 400);
    }

    public function destroy(int $id)
    {
        $result = UserData::deleteUser($id);

        return $result['success']
            ? $this->ok($result['data'], $result['message'], 200)
            : $this->error($result['message'], $result['message'] === 'Usuario no encontrado.' ? 404 : 400);
    }
}
