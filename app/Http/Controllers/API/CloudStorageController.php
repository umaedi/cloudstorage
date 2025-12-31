<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Storage;

class CloudStorageController extends Controller
{
   public function upload(Request $request)
   {
        $validator = Validator::make($request->all(), [
            'image'     => 'required|image|mimes:jpeg,jpg,png',
            'quality'   => 'required|integer|min:1|max:100',
            'directory' => 'required|string',
        ], [
            'image.required'     => 'Gambar wajib diupload',
            'image.image'        => 'File harus berupa gambar',
            'image.mimes'        => 'Gambar wajib format JPEG, JPG, atau PNG',
            'quality.required'   => 'Ukuran kualitas gambar wajib diisi',
            'quality.integer'    => 'Ukuran kualitas gambar wajib angka dari 1-100',
            'directory.required' => 'Nama folder wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Gambar tidak dapat diproses!',
                'errors'  => $validator->errors()
            ], 422);
        }

        $imageFile = $request->file('image');
        $quality   = (int) $request->quality;
        $directory = trim($request->directory, '/');
        $fileName  = $request->file_name ?? uniqid();
        $filePath  = "{$directory}/{$fileName}.webp";

        // Temporary file (hemat memory)
        $tempPath = tempnam(sys_get_temp_dir(), 'img_');

        try {
            // Temp file (hindari RAM membengkak)
            $tempPath = tempnam(sys_get_temp_dir(), 'img_');

            // ImageManager v3
            $manager = new ImageManager(new Driver());

            // Read image
            $image = $manager->read($imageFile->getRealPath());

            // Auto orient (EXIF)
            $image = $image->orient();

            // Encode ke WEBP (v3 style)
            $encoded = $image->toWebp($quality);

            // Simpan ke temporary file
            file_put_contents($tempPath, $encoded->toString());

            // Upload ke S3 via stream
            $stream = fopen($tempPath, 'rb');
            Storage::disk('s3')->put($filePath, $stream);
            fclose($stream);

            $fileSizeInKB = round(filesize($tempPath) / 1024, 2);

            // 🔥 Release memory explicitly
            unset($image, $encoded, $manager);

            // Hapus temp file
            @unlink($tempPath);

        } catch (\Throwable $e) {
            @unlink($tempPath ?? null);

            Log::error('Image compression failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca atau mengompres gambar.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Gambar berhasil dikompres dan diupload.',
            'data' => [
                'file_name' => "{$fileName}.webp",
                'file_size' => $fileSizeInKB,
                'url'       => Storage::disk('s3')->temporaryUrl($filePath, now()->addMinutes(5)),
                'directory' => $filePath,
            ]
        ], 200);
    }

    public function cloudStream(string $pathFile)
    {
        // 🔐 Sanitasi path (cegah traversal)
        $pathFile = str_replace('|', '/', $pathFile);
        $pathFile = ltrim($pathFile, '/');

        if (str_contains($pathFile, '..')) {
            abort(404);
        }

        // 🔐 Validasi extension
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $extension  = strtolower(pathinfo($pathFile, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExt, true)) {
            abort(404);
        }

        try {
            // ⛔ Tidak pakai exists() → langsung stream
            $stream = Storage::disk('s3')->readStream($pathFile);

            if ($stream === false) {
                abort(404);
            }

            // MIME type via mapping lokal (tanpa request S3)
            $mimeTypes = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
            ];

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type'        => $mimeTypes[$extension] ?? 'application/octet-stream',
                'Content-Disposition'=> 'inline; filename="' . basename($pathFile) . '"',
                'Cache-Control'       => 'public, max-age=31536000, immutable',
            ]);

        } catch (\Throwable $e) {
            abort(404);
        }
    }
}
