<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FCMController extends Controller
{
    public function getTokens()
    {
        $fcmTokens = FcmToken::whereNotNull('fcm_token')->get();
        $tokens = $fcmTokens->pluck('fcm_token')->toArray();
        return $tokens;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required',
            'user_id'   => 'required'
        ], [
            'fcm_token.required' => 'FMC Token wajib diisi!',
            'user_id.required'   => 'User ID wajib diisi!'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        $fcmToken = FcmToken::updateOrCreate(
            ['user_id' => $data['user_id']],  
            ['fcm_token' => $data['fcm_token']] 
        );

        Log::info('FCM Token berhasil disimpan', ['data' => $fcmToken]);
        return $this->success('FCM Token berhasil disimpan atau diperbarui', $fcmToken, 201);
    }

}
