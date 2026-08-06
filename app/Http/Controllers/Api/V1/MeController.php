<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Auth\IdentityProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request, IdentityProvider $identityProvider): JsonResponse
    {
        $user = $identityProvider->resolveIdentity($request);

        abort_unless($user !== null, 401);

        return response()->json([
            'data' => [
                'locale' => $user->locale,
            ],
        ]);
    }
}
