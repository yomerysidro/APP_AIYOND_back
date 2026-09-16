<?php

namespace App\Traits;

/*
|--------------------------------------------------------------------------
| Api Responser Trait
|--------------------------------------------------------------------------
|
| This trait will be used for any response we sent to clients.
|
*/

trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param  array|string  $data
     * @param  int|null  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function ok($data, string $message = 'La operación ha tenido éxito', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param  array|string|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function information(string $message, int $code, $data = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param  array|string|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message, int $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], $code);
    }
}
