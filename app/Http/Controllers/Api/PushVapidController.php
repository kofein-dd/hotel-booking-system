<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PushVapidController extends Controller
{
    /**
     * Получить VAPID public key
     */
    public function getPublicKey()
    {
        $publicKey = config('webpush.vapid.public_key');

        if (empty($publicKey)) {
            return response()->json([
                'error' => 'VAPID public key not configured'
            ], 500);
        }

        return response()->json([
            'publicKey' => $publicKey
        ]);
    }
}
