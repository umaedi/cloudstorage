<?php

namespace App\Http\Controllers;
use Illuminate\Http\Response;
abstract class Controller
{
    protected function error($errors = null, int $code = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        $response = [
            'code'    => $code,
            'success' => false,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function success($message = 'Success!', $data = null,  int $code = Response::HTTP_OK)
    {
        $response = [
            'code'      => $code,
            'success'   => true,
            'message'   => $message,    
        ];

        if (!is_null($data)) {
            $response['metadata'] = $data;
        }

        return response()->json($response, $code);
    }

    protected function responseWithPaginate($message, $data, $page, $limit, $total)
    {
        return response()->json([
            'success'   => true,
            'message'   => $message,
            'metadata' => $data,
            'metapage' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK);
    }
}
