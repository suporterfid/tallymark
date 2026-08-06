<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserLocaleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(['en', 'pt-BR'])],
        ]);

        $request->user()->update(['locale' => $validated['locale']]);

        return response()->json([
            'data' => [
                'locale' => $validated['locale'],
            ],
        ]);
    }
}
