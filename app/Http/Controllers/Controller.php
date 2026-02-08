<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Успешный ответ.
     */
    protected function success($message = 'Успешно', $data = null, $redirect = null)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($redirect) {
            $response['redirect'] = $redirect;
        }

        return response()->json($response);
    }

    /**
     * Ответ с ошибкой.
     */
    protected function error($message = 'Произошла ошибка', $errors = null, $code = 400)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
