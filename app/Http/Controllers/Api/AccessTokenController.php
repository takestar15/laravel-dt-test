<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\IssueAccessTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueAccessTokenRequest;
use Illuminate\Http\JsonResponse;

class AccessTokenController extends Controller
{
    /**
     * Issue a bearer token for API access.
     *
     * Exchange a valid email and password for a personal access token that can
     * be used as a Bearer token on the protected translation endpoints.
     *
     * @unauthenticated
     */
    public function __invoke(
        IssueAccessTokenRequest $request,
        IssueAccessTokenAction $issueAccessToken,
    ): JsonResponse {
        $token = $issueAccessToken->handle($request->validated());

        return response()->json([
            'token' => $token['token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at'],
        ]);
    }
}
