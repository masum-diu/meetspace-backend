<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function verify(): JsonResponse
    {
        return response()->json(['verified' => true]);
    }
}
