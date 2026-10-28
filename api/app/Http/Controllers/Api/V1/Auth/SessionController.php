<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Presenters\AuthSessionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Contract v0.4 §4.3–§4.4.
 */
class SessionController extends Controller
{
    public function show(Request $request, AuthSessionPresenter $presenter): JsonResponse
    {
        return response()->json(['data' => $presenter->session($request->user())]);
    }

    /**
     * Allowed for suspended users too (contract §3).
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
