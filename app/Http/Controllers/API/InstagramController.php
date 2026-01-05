<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class InstagramController extends Controller
{
    public function index(Request $request)
    {
        $token = $request->token ?? config("app.token_instagram");
        $mediaType = $request->media_type;

        $cacheKey = 'instagram:index:' . md5($token . $mediaType);

        $media = Cache::remember($cacheKey, 300, function () use ($token, $mediaType) {
            $response = Http::get('https://graph.instagram.com/me/media', [
                'access_token' => $token,
                'fields' => 'id,caption,media_url,media_type',
                'limit' => 15,
            ]);

            $collection = collect($response->json('data'));

            if ($mediaType) {
                $collection = $collection->filter(
                    fn ($item) => $item['media_type'] === $mediaType
                );
            }

            return $collection->values()->take(6);
        });

        return response()->json([
            'success' => true,
            'message' => 'List data postingan instagram',
            'data'    => $media,
        ]);
    }

    public function show(Request $request, $id)
    {
        $token = $request->input('token', config("app.token_instagram"));
        $cacheKey = 'instagram:show:' . md5($id . $token);

        $data = Cache::remember($cacheKey, 300, function () use ($id, $token) {
            $response = Http::get("https://graph.instagram.com/{$id}", [
                'access_token' => $token,
                'fields' => 'id,caption,media_url,media_type,permalink'
            ]);

            return $response->json();
        });

        return response()->json([
            'success' => true,
            'message' => 'Detail postingan instagram',
            'data'    => $data,
        ]);
    }
}
