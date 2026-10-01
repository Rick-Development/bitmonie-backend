<?php

namespace App\Traits;


trait ApiResponse
{
    public function withSuccess($msg)
    {
        return [
            'status' => 'success',
            'message' => $msg
        ];
    }

    public function withError($msg)
    {
        return [
            'status' => 'error',
            'message' => $msg
        ];
    }
}
