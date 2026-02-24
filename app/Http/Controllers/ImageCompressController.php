<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class ImageCompressController extends Controller
{
    public function s3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png',
            'quality' => 'required|integer|min:1|max:100',
            'directory' => 'required|string',
        ], [
            'image.required' => 'Gambar wajib diupload',
            'image.image' => 'File harus berupa gambar',
            'image.mimes' => 'Gambar wajib format JPEG, JPG, atau PNG',
            'quality.required' => 'Ukuran kualitas gambar wajib diisi',
            'quality.integer' => 'Ukuran kualitas gambar wajib angka dari 1-100',
            'directory.required' => 'Nama folder wajib diisi',
        ]);

        if($validator->fails()) {
            return response()->json([
                'success' => false,
                'message'   => 'Gambar tidak dapat diproses!',
                'errors'    => $validator->errors()
            ],422);
        }

        $image = $request->file('image');
        $quality = (int) $request->input('quality');
        $directory = $request->input('directory');
        $fileName = $request->input('file_name') ?? uniqid();
        $filePath = "{$directory}/{$fileName}" . '.webp';

        try {
            $compressedImage = Image::make($image->getRealPath())
                ->orientate()
                ->encode('webp', $quality);

            Storage::disk('s3')->put($filePath, (string) $compressedImage);
            $fileSizeInBytes = strlen((string) $compressedImage);
            $fileSizeInKB = round($fileSizeInBytes / 1024, 2);
            
        } catch (\Exception $e) {
            Log::error("Gagal mengompres gambar: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membaca atau mengompres gambar.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Gambar berhasil dikompres dan diupload.',
            'data' => [
                'file_name' => $fileName . '.webp',
                'file_size' => $fileSizeInKB,
                'url' => Storage::disk('s3')->temporaryUrl($filePath, now()->addMinutes(5)),
                'directory' => $filePath
            ]
        ],200);
    }
}
