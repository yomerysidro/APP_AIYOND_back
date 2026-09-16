<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Data\Auth\AuthData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
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
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = AuthData::register($validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 201)
            : $this->error($response['message'], 400);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->information('Datos de validación incorrectos.', 422, $validator->errors());
        }

        $response = AuthData::login($validator->validated());

        return $response['success']
            ? $this->ok($response['data'], $response['message'], 200)
            : $this->error($response['message'], 401);
    }

    public function refresh()
    {
        $response = AuthData::refresh();

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function logout()
    {
        $response = AuthData::logout();

        return $this->ok($response['data'], $response['message'], 200);
    }

    public function me()
    {
        $response = AuthData::me();

        return $this->ok($response['data'], $response['message'], 200);
    }
}
